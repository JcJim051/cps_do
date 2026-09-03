<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\SecopVinculo;
use App\Models\Seguimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecopConciliacionService
{
    private SecopVigenciaService $vigencias;

    public function __construct(
        private DatosAbiertosSecopService $secop,
        private SecopNormalizer $normalizer,
        private SecopEntidadService $entidades,
        ?SecopVigenciaService $vigencias = null,
    ) {
        $this->vigencias = $vigencias ?? new SecopVigenciaService();
    }

    public function conciliarPersona(
        Persona $persona,
        string $desde = '2024-01-01',
        ?int $anio = null,
        bool $consultarDocumentoDiferente = true,
        ?int $vigenciaEjecucion = null,
        array $nitEntidades = [],
        array $secretariaIds = [],
    ): array
    {
        $documento = $this->normalizer->documento($persona->cedula_o_nit);
        $seguimientosQuery = $persona->seguimientos()->where('tipo', 'contrato');
        if ($secretariaIds !== []) {
            $seguimientosQuery->whereIn('secretaria_id', array_map('intval', $secretariaIds));
        }
        if ($anio !== null && $vigenciaEjecucion === null) {
            $seguimientosQuery->where('anio', $anio);
        }
        $seguimientos = $seguimientosQuery
            ->with(['secretaria', 'estadoContrato', 'vinculoSecop.ultimaInstantanea', 'prevalidacionOrigen.fuente'])
            ->orderBy('anio')
            ->orderBy('id')
            ->get();
        if ($vigenciaEjecucion !== null) {
            $seguimientos = $seguimientos
                ->filter(fn (Seguimiento $seguimiento) => $this->vigencias->seguimientoPertenece($seguimiento, $vigenciaEjecucion))
                ->values();
        }

        $consultaSecopDisponible = true;
        try {
            $scopeSuffix = $nitEntidades !== [] ? '_entidades_'.sha1(implode('|', $nitEntidades)) : '';
            $cacheSuffix = ($vigenciaEjecucion !== null ? 'vigencia_'.$vigenciaEjecucion : sha1($desde)).$scopeSuffix;
            $contratos = Cache::remember(
                'datos_abiertos_contratos_'.$documento.'_'.$cacheSuffix,
                600,
                fn () => $vigenciaEjecucion !== null
                    ? $this->secop->consultarPorDocumentoVigencia($documento, $vigenciaEjecucion, 1000, $nitEntidades)
                    : $this->secop->consultarPorDocumento($documento, $desde),
            );
        } catch (\Throwable $e) {
            $consultaSecopDisponible = false;
            $contratos = $seguimientos
                ->pluck('vinculoSecop')
                ->filter()
                ->map(function (SecopVinculo $vinculo) {
                    $datos = $vinculo->ultimaInstantanea?->datos;
                    if (!is_array($datos)) {
                        return null;
                    }

                    return array_merge($datos, [
                        'fuente_codigo' => $datos['fuente_codigo'] ?? $vinculo->fuente_secop,
                        'identificador_externo' => $datos['identificador_externo'] ?? $vinculo->identificador_externo,
                        'referencia_contrato' => $datos['referencia_contrato'] ?? $vinculo->referencia_contrato,
                        'tipo_registro' => $datos['tipo_registro'] ?? 'contrato',
                    ]);
                })
                ->filter()
                ->values()
                ->all();

            Log::warning('SECOP no respondió al conciliar la persona; se usarán las últimas instantáneas locales.', [
                'persona_id' => $persona->id,
                'error' => $e->getMessage(),
            ]);
        }

        $contratos = collect($contratos);
        if ($vigenciaEjecucion !== null) {
            $contratos = $contratos
                ->filter(fn (array $contrato) => $this->vigencias->contratoPertenece($contrato, $vigenciaEjecucion))
                ->values();
        }
        $identificadores = $contratos
            ->groupBy(fn (array $row) => (string) ($row['fuente_codigo'] ?? ''))
            ->map(fn (Collection $rows) => $rows->pluck('identificador_externo')->filter()->unique()->values());
        $usedQuery = SecopVinculo::query()->whereNotNull('seguimiento_id');
        $usedQuery->where(function ($query) use ($identificadores) {
            foreach ($identificadores as $fuente => $ids) {
                if ($fuente !== '' && $ids->isNotEmpty()) {
                    $query->orWhere(fn ($sourceQuery) => $sourceQuery
                        ->where('fuente_secop', $fuente)
                        ->whereIn('identificador_externo', $ids));
                }
            }
        });
        $used = $identificadores->flatten()->isEmpty()
            ? collect()
            : $usedQuery->get(['id', 'seguimiento_id', 'fuente_secop', 'identificador_externo'])
            ->mapWithKeys(fn ($link) => [
                $link->fuente_secop.'|'.$link->identificador_externo => $link->seguimiento_id,
            ]);

        $result = $this->conciliar($persona, $seguimientos, $contratos, $used);
        $result['consulta_secop_disponible'] = $consultaSecopDisponible;
        if (!$consultarDocumentoDiferente || !$consultaSecopDisponible) {
            return $result;
        }

        $result['filas'] = $result['filas']->map(function (array $row) use ($documento) {
            if ($row['estado'] !== 'sin_resultado' || !$row['seguimiento']->numero_contrato || !$row['seguimiento']->anio) {
                return $row;
            }

            $nit = $this->entidades->nitParaSeguimiento($row['seguimiento']);
            if (!$nit) {
                return $row;
            }

            try {
                $others = Cache::remember(
                    'secop_referencia_'.$row['seguimiento']->id.'_'.sha1($nit.'|'.$row['seguimiento']->numero_contrato.'|'.$row['seguimiento']->anio),
                    600,
                    fn () => $this->secop->consultarReferenciaEntidad(
                        $row['seguimiento']->numero_contrato,
                        (int) $row['seguimiento']->anio,
                        $nit,
                    ),
                );
            } catch (\Throwable) {
                return $row;
            }

            $differentDocument = collect($others)->first(fn (array $candidate) =>
                $this->normalizer->documento($candidate['documento'] ?? '') !== $documento
            );
            if (!$differentDocument) {
                return $row;
            }

            $row['estado'] = 'documento_diferente';
            $row['estado_label'] = 'Contrato asociado a otra cédula';
            $row['candidato'] = $differentDocument;
            $row['razones'] = ['Número, entidad y vigencia coinciden', 'La cédula de SECOP es diferente'];
            $row['diferencia_valor_pct'] = $this->normalizer->diferenciaPorcentual(
                $row['seguimiento']->valor_total_contrato ?: $row['seguimiento']->valor_total,
                $differentDocument['valor_total_con_adiciones'] ?? null,
            );
            $row['diferencia_fin_dias'] = $this->dateDifference(
                $row['seguimiento']->fecha_finalizacion,
                $differentDocument['fecha_fin'] ?? null,
            );

            return $row;
        });
        $result['metricas'] = array_merge(
            $this->metrics($result['filas']),
            ['solo_secop' => $result['contratos_sin_seguimiento']->count()],
        );

        return $result;
    }

    public function conciliar(Persona $persona, Collection $seguimientos, Collection $contratos, ?Collection $used = null): array
    {
        $used ??= collect();
        $documento = $this->normalizer->documento($persona->cedula_o_nit);
        $contratos = $contratos
            ->filter(fn (array $row) => ($row['tipo_registro'] ?? 'contrato') === 'contrato')
            ->values();

        $byContract = $contratos->groupBy(function (array $row) {
            return $this->normalizer->contrato(
                $row['referencia_contrato'] ?? '',
                $row['fecha_firma'] ?? null,
            )['clave'] ?? '__sin_clave__';
        });

        $assigned = collect();
        $rows = $seguimientos->map(function (Seguimiento $seguimiento) use (
            $persona,
            $documento,
            $byContract,
            $used,
            $assigned,
        ) {
            $number = $this->normalizer->contrato($seguimiento->numero_contrato, $seguimiento->anio);
            $eligible = filled($seguimiento->numero_contrato)
                || ($seguimiento->aut_despacho && $seguimiento->aut_planeacion);
            $candidates = $number['clave'] ? collect($byContract->get($number['clave'], []))->values() : collect();
            $candidate = $candidates->count() === 1 ? $candidates->first() : null;
            $reasons = [];
            $status = 'sin_resultado';
            $label = 'Sin contrato encontrado';
            $safe = false;

            if (!$eligible) {
                $status = 'no_habilitado';
                $label = 'Pendiente de autorizaciones';
            } elseif ($seguimiento->vinculoSecop) {
                $status = 'vinculado';
                $label = 'Contrato vinculado';
                $candidate = $candidates->first(fn (array $row) =>
                    ($row['fuente_codigo'] ?? '') === $seguimiento->vinculoSecop->fuente_secop
                    && (string) ($row['identificador_externo'] ?? '') === $seguimiento->vinculoSecop->identificador_externo
                ) ?: $candidate;
            } elseif ($candidates->count() > 1) {
                $status = 'ambiguo';
                $label = 'Varias coincidencias';
            } elseif ($candidate) {
                $candidateDocument = $this->normalizer->documento($candidate['documento'] ?? '');
                $documentMatches = $candidateDocument !== '' && $candidateDocument === $documento;
                $nit = $this->entidades->nitParaSeguimiento($seguimiento);
                $entityMatches = $nit ? $this->normalizer->nitCoincide($nit, $candidate['nit_entidad'] ?? null) : null;
                $key = ($candidate['fuente_codigo'] ?? '').'|'.($candidate['identificador_externo'] ?? '');
                $owner = $used->get($key);

                if ($documentMatches) {
                    $reasons[] = 'Cédula exacta';
                }
                $reasons[] = 'Número y vigencia exactos';
                if ($entityMatches === true) {
                    $reasons[] = 'Entidad contratante exacta';
                } elseif ($entityMatches === null) {
                    $reasons[] = 'Entidad pendiente de parametrizar';
                } else {
                    $reasons[] = 'Entidad diferente';
                }

                if ($owner && (int) $owner !== (int) $seguimiento->id) {
                    $status = 'conflicto';
                    $label = 'Contrato usado por otro seguimiento';
                } elseif (!$documentMatches || $entityMatches === false) {
                    $status = 'requiere_revision';
                    $label = 'Requiere revisión';
                } elseif ($entityMatches === null) {
                    $status = 'requiere_configuracion';
                    $label = 'Falta parametrizar entidad';
                } else {
                    $status = 'exacto';
                    $label = 'Coincidencia exacta';
                    $safe = true;
                    $assigned->push($key);
                }
            } elseif (!$seguimiento->numero_contrato) {
                $label = 'Aún sin número de contrato';
            }

            $plannedValue = $seguimiento->valor_total_contrato ?: $seguimiento->valor_total;
            $observedValue = $candidate['valor_total_con_adiciones'] ?? null;

            return [
                'seguimiento' => $seguimiento,
                'estado' => $status,
                'estado_label' => $label,
                'seguro' => $safe,
                'candidato' => $candidate,
                'cantidad_candidatos' => $candidates->count(),
                'razones' => $reasons,
                'diferencia_valor_pct' => $this->normalizer->diferenciaPorcentual($plannedValue, $observedValue),
                'diferencia_fin_dias' => $this->dateDifference($seguimiento->fecha_finalizacion, $candidate['fecha_fin'] ?? null),
            ];
        })->values();

        // Un candidato ambiguo o pendiente de configuración no es un contrato
        // "solo SECOP": si su número/vigencia ya existe localmente, continúa
        // siendo una posible contraparte que debe revisarse.
        $localContractKeys = $seguimientos
            ->map(fn (Seguimiento $seguimiento) => $this->normalizer->contrato(
                $seguimiento->numero_contrato,
                $seguimiento->anio,
            )['clave'])
            ->filter()
            ->unique();

        $unmatched = $contratos->reject(function (array $candidate) use ($assigned, $used, $localContractKeys) {
            $key = ($candidate['fuente_codigo'] ?? '').'|'.($candidate['identificador_externo'] ?? '');
            $contractKey = $this->normalizer->contrato(
                $candidate['referencia_contrato'] ?? '',
                $candidate['fecha_firma'] ?? null,
            )['clave'];

            return $assigned->contains($key)
                || $used->has($key)
                || ($contractKey && $localContractKeys->contains($contractKey));
        })->values();

        $metrics = $this->metrics($rows);
        $metrics['solo_secop'] = $unmatched->count();

        return [
            'persona' => $persona,
            'filas' => $rows,
            'contratos_sin_seguimiento' => $unmatched,
            'metricas' => $metrics,
        ];
    }

    private function metrics(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'exactos' => $rows->where('estado', 'exacto')->count(),
            'vinculados' => $rows->where('estado', 'vinculado')->count(),
            'revision' => $rows->whereIn('estado', [
                'ambiguo', 'conflicto', 'requiere_revision', 'requiere_configuracion', 'documento_diferente',
            ])->count(),
            'sin_resultado' => $rows->where('estado', 'sin_resultado')->count(),
            'no_habilitados' => $rows->where('estado', 'no_habilitado')->count(),
        ];
    }

    private function dateDifference(mixed $planned, mixed $observed): ?int
    {
        if (!$planned || !$observed) {
            return null;
        }

        try {
            return (int) $planned->diffInDays($observed, false);
        } catch (\Throwable) {
            return null;
        }
    }
}
