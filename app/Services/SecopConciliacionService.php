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
    private array $nitCache = [];

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

    /** Precalienta el mismo caché que usa conciliarPersona para evitar una consulta por cédula. */
    public function cacheContratosPersonaVigencia(
        Persona $persona,
        int $anio,
        array $nitEntidades,
        array $contratos,
    ): void {
        $documento = $this->normalizer->documento($persona->cedula_o_nit);
        $scopeSuffix = $nitEntidades !== [] ? '_entidades_'.sha1(implode('|', $nitEntidades)) : '';
        Cache::put(
            'datos_abiertos_contratos_'.$documento.'_vigencia_'.$anio.$scopeSuffix,
            $contratos,
            600,
        );
    }

    public function conciliar(Persona $persona, Collection $seguimientos, Collection $contratos, ?Collection $used = null): array
    {
        $this->nitCache = [];
        $used ??= collect();
        $documento = $this->normalizer->documento($persona->cedula_o_nit);
        $contratos = $contratos
            ->filter(fn (array $row) => ($row['tipo_registro'] ?? 'contrato') === 'contrato')
            ->values();

        $assigned = collect();
        $rows = $seguimientos->map(function (Seguimiento $seguimiento) use (
            $documento,
            $contratos,
            $used,
            $assigned,
        ) {
            $number = $this->normalizer->contrato($seguimiento->numero_contrato, $seguimiento->anio);
            $eligible = filled($seguimiento->numero_contrato)
                || ($seguimiento->aut_despacho && $seguimiento->aut_planeacion);
            $entityCandidates = $this->candidatesForSeguimiento($seguimiento, $contratos, $documento);
            $candidates = $number['clave']
                ? $entityCandidates->filter(fn (array $candidate) => $this->contractKey($candidate) === $number['clave'])->values()
                : collect();
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
                $candidate = $contratos->first(fn (array $row) =>
                    ($row['fuente_codigo'] ?? '') === $seguimiento->vinculoSecop->fuente_secop
                    && (string) ($row['identificador_externo'] ?? '') === $seguimiento->vinculoSecop->identificador_externo
                ) ?: $candidate;
            } elseif ($candidates->count() > 1) {
                $status = 'ambiguo';
                $label = 'Varias coincidencias';
            } elseif ($candidate) {
                $candidateDocument = $this->normalizer->documento($candidate['documento'] ?? '');
                $documentMatches = $candidateDocument !== '' && $candidateDocument === $documento;
                $nit = $this->nitFor($seguimiento);
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
                } elseif ($assigned->contains($key)) {
                    $status = 'conflicto';
                    $label = 'Contrato repetido en varios seguimientos';
                    $reasons[] = 'El mismo contrato ya fue asignado a otro Seguimiento de esta persona';
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
            } elseif (!$seguimiento->numero_contrato && $entityCandidates->isNotEmpty()) {
                $label = 'Pendiente de comparar fechas y valores';
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
                'puntaje' => null,
                'margen' => null,
                '_candidatos_aproximados' => $entityCandidates,
                'diferencia_valor_pct' => $this->normalizer->diferenciaPorcentual($plannedValue, $observedValue),
                'diferencia_fin_dias' => $this->dateDifference($seguimiento->fecha_finalizacion, $candidate['fecha_fin'] ?? null),
            ];
        })->values();

        // Segunda pasada: cuando el número falta o no resuelve de forma única,
        // se comparan fechas y valores. La asignación automática exige que la
        // pareja sea la mejor opción tanto para el Seguimiento como para el
        // contrato SECOP, evitando cruces por orden de procesamiento.
        $pairs = collect();
        foreach ($rows as $index => $row) {
            if (!in_array($row['estado'], ['sin_resultado', 'ambiguo'], true)) {
                continue;
            }
            foreach ($row['_candidatos_aproximados'] as $candidate) {
                $key = $this->candidateKey($candidate);
                $owner = $used->get($key);
                if ($assigned->contains($key) || ($owner && (int) $owner !== (int) $row['seguimiento']->id)) {
                    continue;
                }
                $score = $this->approximateScore($row['seguimiento'], $candidate);
                if ($score['puntaje'] <= 0) {
                    continue;
                }
                $pairs->push($score + [
                    'row_index' => $index,
                    'seguimiento_id' => (int) $row['seguimiento']->id,
                    'candidate_key' => $key,
                    'candidato' => $candidate,
                ]);
            }
        }

        $byTracking = $pairs->groupBy('seguimiento_id')->map(fn (Collection $group) => $group->sortByDesc('puntaje')->values());
        $byCandidate = $pairs->groupBy('candidate_key')->map(fn (Collection $group) => $group->sortByDesc('puntaje')->values());

        foreach ($rows as $index => $row) {
            if (!in_array($row['estado'], ['sin_resultado', 'ambiguo'], true)) {
                continue;
            }
            $ranked = $byTracking->get((int) $row['seguimiento']->id, collect());
            $best = $ranked->first();
            if (!$best) {
                continue;
            }
            $secondScore = (int) data_get($ranked->get(1), 'puntaje', 0);
            $margin = (int) $best['puntaje'] - $secondScore;
            $candidateRanked = $byCandidate->get($best['candidate_key'], collect());
            $candidateSecond = (int) data_get($candidateRanked->get(1), 'puntaje', 0);
            $candidateMargin = (int) $best['puntaje'] - $candidateSecond;
            $mutualBest = (int) data_get($candidateRanked->first(), 'seguimiento_id') === (int) $row['seguimiento']->id;
            $localNumber = $this->normalizer->contrato($row['seguimiento']->numero_contrato, $row['seguimiento']->anio);
            $candidateNumber = $this->normalizer->contrato(
                $best['candidato']['referencia_contrato'] ?? '',
                $best['candidato']['fecha_firma'] ?? null,
            );
            $numberAllowsAutomatic = blank($row['seguimiento']->numero_contrato)
                || ($localNumber['clave'] && $localNumber['clave'] === $candidateNumber['clave']);
            $safe = $best['puntaje'] >= 75
                && $margin >= 15
                && $candidateMargin >= 15
                && $mutualBest
                && $best['evidencia_fecha']
                && $best['evidencia_valor']
                && $numberAllowsAutomatic;

            $row['candidato'] = $best['candidato'];
            $row['cantidad_candidatos'] = $ranked->count();
            $row['puntaje'] = $best['puntaje'];
            $row['margen'] = min($margin, $candidateMargin);
            $row['razones'] = $best['razones'];
            $row['diferencia_valor_pct'] = $best['diferencia_valor_pct'];
            $row['diferencia_fin_dias'] = $best['diferencia_fin_dias'];
            if ($safe) {
                $row['estado'] = 'aproximado';
                $row['estado_label'] = 'Coincidencia aproximada alta';
                $row['seguro'] = true;
                $assigned->push($best['candidate_key']);
            } else {
                $row['estado'] = 'requiere_revision';
                $row['estado_label'] = $numberAllowsAutomatic
                    ? 'Coincidencia aproximada por revisar'
                    : 'Número local diferente: revisar';
                $row['seguro'] = false;
            }
            $rows[$index] = $row;
        }

        $rows = $rows->map(function (array $row) {
            unset($row['_candidatos_aproximados']);
            return $row;
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

    private function candidatesForSeguimiento(Seguimiento $seguimiento, Collection $contracts, string $document): Collection
    {
        $nit = $this->nitFor($seguimiento);
        $secretaria = $seguimiento->relationLoaded('secretaria') ? $seguimiento->getRelation('secretaria') : null;
        $dependencyCode = $this->identity($secretaria?->codigo_pci_secop);
        $dependencyName = $this->normalizer->unidadContratante($secretaria?->nombre_unidad_secop);

        return $contracts->filter(function (array $candidate) use ($seguimiento, $document, $nit, $dependencyCode, $dependencyName) {
            $candidateDocument = $this->normalizer->documento($candidate['documento'] ?? '');
            if ($candidateDocument === '' || $candidateDocument !== $document) {
                return false;
            }
            if ($nit && !$this->normalizer->nitCoincide($nit, $candidate['nit_entidad'] ?? null)) {
                return false;
            }
            $candidateCode = $this->identity($candidate['codigo_dependencia'] ?? null);
            if ($dependencyCode !== '' && $candidateCode !== '' && $dependencyCode !== $candidateCode) {
                return false;
            }
            $candidateName = $this->normalizer->unidadContratante($candidate['nombre_dependencia'] ?? null);
            if ($dependencyName !== '' && $candidateName !== '' && $dependencyName !== $candidateName) {
                return false;
            }
            return $this->candidateBelongsToYear($candidate, (int) $seguimiento->anio);
        })->values();
    }

    /** @return array{puntaje:int,razones:array,evidencia_fecha:bool,evidencia_valor:bool,diferencia_valor_pct:?float,diferencia_fin_dias:?int} */
    private function approximateScore(Seguimiento $seguimiento, array $candidate): array
    {
        $score = 0;
        $reasons = ['Cédula exacta', 'Entidad contratante exacta', 'Vigencia compatible'];
        $dateEvidence = false;
        $valueEvidence = false;
        $startDelay = null;

        $localStart = $this->date($seguimiento->fecha_acta_inicio);
        $candidateStart = $this->date($candidate['fecha_inicio'] ?? null);
        if ($localStart && $candidateStart) {
            $startDelay = $localStart->diffInDays($candidateStart, false);
            if ($startDelay >= -7 && $startDelay <= 60) {
                $dateEvidence = true;
                $startPoints = $startDelay <= 7
                    ? 25
                    : max(10, 25 - (int) round((($startDelay - 7) / 53) * 15));
                $score += $startPoints;
                $reasons[] = $startDelay >= 0
                    ? "Inicio SECOP {$startDelay} día(s) después del planeado"
                    : 'Inicio SECOP '.abs($startDelay).' día(s) antes del planeado';
            }
        }

        $localEnd = $this->date($seguimiento->fecha_finalizacion_vigente);
        $candidateEnd = $this->date($candidate['fecha_fin'] ?? null);
        $endDifference = null;
        if ($localEnd && $candidateEnd) {
            $endDifference = $localEnd->diffInDays($candidateEnd, false);
            $absolute = abs($endDifference);
            if ($absolute <= 60) {
                $dateEvidence = true;
                $score += max(5, 15 - (int) round(($absolute / 60) * 10));
                $reasons[] = "Finalización con diferencia de {$endDifference} día(s)";
            }
        }

        $plannedValue = (float) ($seguimiento->valor_total_contrato ?: $seguimiento->valor_total ?: 0);
        $observedValues = collect([
            $candidate['valor_contrato'] ?? null,
            $candidate['valor_total_con_adiciones'] ?? null,
        ])->filter(fn ($value) => is_numeric($value) && (float) $value > 0)->map(fn ($value) => (float) $value)->unique();
        $bestValue = null;
        $valueDifferencePct = null;
        $valuePoints = 0;
        if ($plannedValue > 0 && $observedValues->isNotEmpty()) {
            foreach ($observedValues as $observed) {
                $difference = (($observed - $plannedValue) / $plannedValue) * 100;
                $reduction = max(0, -$difference);
                $points = $observed <= $plannedValue
                    ? match (true) {
                        $reduction <= 3 => 40,
                        $reduction <= 10 => 36,
                        $reduction <= 20 => 30,
                        $reduction <= 35 => 22,
                        $reduction <= 50 => 12,
                        default => 0,
                    }
                    : match (true) {
                        $difference <= 3 => 32,
                        $difference <= 10 => 15,
                        default => 0,
                    };

                if ($startDelay !== null && $startDelay > 0 && (float) $seguimiento->valor_mensual > 0) {
                    $expected = max(0, $plannedValue - ($startDelay * ((float) $seguimiento->valor_mensual / 30)));
                    if ($expected > 0) {
                        $expectedDifference = abs($observed - $expected) / $expected * 100;
                        if ($expectedDifference <= 3) $points = max($points, 40);
                        elseif ($expectedDifference <= 7) $points = max($points, 35);
                    }
                }

                if ($points > $valuePoints) {
                    $valuePoints = $points;
                    $bestValue = $observed;
                    $valueDifferencePct = round($difference, 2);
                }
            }
        }
        if ($valuePoints >= 12) {
            $valueEvidence = true;
            $score += $valuePoints;
            $reasons[] = 'Valor SECOP '.($valueDifferencePct > 0 ? '+' : '').number_format((float) $valueDifferencePct, 2, ',', '.').'% frente a Integra';
        }

        $localDays = $seguimiento->tiempo_total_vigente_dias;
        $publishedDays = $this->normalizer->duracionPublicadaDias(
            $candidate['duracion_inicial'] ?? null,
            $candidate['unidad_duracion'] ?? null,
        );
        if ($localDays !== null && $publishedDays !== null) {
            $durationDifference = abs($publishedDays - (int) $localDays);
            if ($durationDifference <= 7) {
                // Un plazo explícitamente publicado en días es evidencia
                // temporal suficiente cuando Integra todavía no tiene fechas.
                $dateEvidence = true;
                if ($durationDifference === 0) $score += 35;
                elseif ($durationDifference <= 3) $score += 28;
                else $score += 18;
                $reasons[] = $durationDifference === 0
                    ? "Plazo publicado exacto: {$publishedDays} días"
                    : "Plazo publicado con diferencia de {$durationDifference} día(s)";
            }
        } elseif ($localDays !== null && $candidateStart && $candidateEnd) {
            $candidateDays = $candidateStart->diffInDays($candidateEnd) + 1;
            $durationDifference = abs($candidateDays - (int) $localDays);
            if ($durationDifference <= 7) $score += 10;
            elseif ($durationDifference <= 30) $score += 6;
            elseif ($durationDifference <= 60) $score += 3;
            if ($durationDifference <= 60) $reasons[] = "Duración con diferencia de {$durationDifference} día(s)";
        }

        $secretaria = $seguimiento->relationLoaded('secretaria') ? $seguimiento->getRelation('secretaria') : null;
        $dependencyCode = $this->identity($secretaria?->codigo_pci_secop);
        $candidateCode = $this->identity($candidate['codigo_dependencia'] ?? null);
        if ($dependencyCode !== '' && $candidateCode !== '' && $dependencyCode === $candidateCode) {
            $score += 10;
            $reasons[] = 'Código de dependencia SECOP exacto';
        } elseif ($candidateCode !== '') {
            $reasons[] = 'Código SECOP observado: '.($candidate['codigo_dependencia'] ?? $candidateCode);
        }
        $dependencyName = $this->normalizer->unidadContratante($secretaria?->nombre_unidad_secop);
        $candidateName = $this->normalizer->unidadContratante($candidate['nombre_dependencia'] ?? null);
        if ($dependencyName !== '' && $candidateName !== '' && $dependencyName === $candidateName) {
            $score += 15;
            $reasons[] = 'Unidad contratante SECOP exacta';
        } elseif ($candidateName !== '') {
            $reasons[] = 'Unidad SECOP observada: '.($candidate['nombre_dependencia'] ?? $candidateName);
        }

        $localNumber = $this->normalizer->contrato($seguimiento->numero_contrato, $seguimiento->anio);
        if ($localNumber['clave'] && $localNumber['clave'] === $this->contractKey($candidate)) {
            $score += 10;
            $reasons[] = 'Número y vigencia exactos';
        }

        return [
            'puntaje' => min(100, $score),
            'razones' => $reasons,
            'evidencia_fecha' => $dateEvidence,
            'evidencia_valor' => $valueEvidence,
            'diferencia_valor_pct' => $bestValue !== null ? $this->normalizer->diferenciaPorcentual($plannedValue, $bestValue) : null,
            'diferencia_fin_dias' => $endDifference,
        ];
    }

    private function candidateBelongsToYear(array $candidate, int $year): bool
    {
        if ($year <= 0) return true;
        $start = $this->date($candidate['fecha_inicio'] ?? null);
        $end = $this->date($candidate['fecha_fin'] ?? null);
        if ($start) {
            $yearStart = \Carbon\Carbon::create($year, 1, 1)->startOfDay();
            $yearEnd = \Carbon\Carbon::create($year, 12, 31)->endOfDay();
            return $start->lte($yearEnd) && (!$end || $end->gte($yearStart));
        }
        $signed = $this->date($candidate['fecha_firma'] ?? null);
        return !$signed || (int) $signed->year === $year;
    }

    private function contractKey(array $candidate): ?string
    {
        return $this->normalizer->contrato(
            $candidate['referencia_contrato'] ?? '',
            $candidate['fecha_firma'] ?? null,
        )['clave'];
    }

    private function candidateKey(array $candidate): string
    {
        return (string) ($candidate['fuente_codigo'] ?? '').'|'.(string) ($candidate['identificador_externo'] ?? '');
    }

    private function identity(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function nitFor(Seguimiento $seguimiento): ?string
    {
        $key = spl_object_id($seguimiento);
        if (!array_key_exists($key, $this->nitCache)) {
            $this->nitCache[$key] = $this->entidades->nitParaSeguimiento($seguimiento);
        }
        return $this->nitCache[$key];
    }

    private function date(mixed $value): ?\Carbon\Carbon
    {
        if (!$value) return null;
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function metrics(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'exactos' => $rows->where('estado', 'exacto')->count(),
            'aproximados' => $rows->where('estado', 'aproximado')->count(),
            'automaticos' => $rows->whereIn('estado', ['exacto', 'aproximado'])->count(),
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
