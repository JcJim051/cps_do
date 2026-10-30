<?php

namespace App\Services;

use App\Jobs\ProcesarVinculacionSecopExacta;
use App\Models\Persona;
use App\Models\SecopConciliacionLote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SecopVinculacionMasivaService
{
    public function __construct(
        private SecopConciliacionService $conciliacion,
        private SecopVinculacionService $vinculacion,
        private SecopAlcanceEntidadService $alcanceEntidades,
        private DatosAbiertosSecopService $secop,
    ) {
    }

    /** @return array{lote: SecopConciliacionLote, creado: bool} */
    public function start(array $filters, ?int $userId): array
    {
        $scope = $this->alcanceEntidades->configuracionCompleta();
        if (!empty($filters['secretaria_id']) && !in_array((int) $filters['secretaria_id'], $scope['secretaria_ids'], true)) {
            throw ValidationException::withMessages([
                'secretaria_id' => 'La entidad seleccionada no está incluida en el alcance SECOP departamental.',
            ]);
        }

        return Cache::lock('secop-vinculacion-exacta-inicio', 10)->block(3, function () use ($filters, $userId, $scope) {
            $active = SecopConciliacionLote::query()
                ->whereIn('estado', ['pendiente', 'procesando'])
                ->latest('id')
                ->first();
            if ($active) {
                return ['lote' => $active, 'creado' => false];
            }

            $filters['anio'] = 2026;
            $total = $this->eligiblePersonas($filters, 0, $scope['secretaria_ids'])->count();
            $lote = SecopConciliacionLote::create([
                'anio' => 2026,
                'secretaria_id' => $filters['secretaria_id'] ?? null,
                'estado_contrato_id' => $filters['estado_contrato_id'] ?? null,
                'secretaria_ids' => $scope['secretaria_ids'],
                'nit_entidades' => $scope['nit_entidades'],
                'iniciado_por' => $userId,
                'estado' => $total > 0 ? 'pendiente' : 'finalizado',
                'total_personas' => $total,
                'resultados' => $this->emptyResults(),
                'finalizado_at' => $total > 0 ? null : now(),
            ]);

            if ($total > 0) {
                ProcesarVinculacionSecopExacta::dispatch($lote->id);
            }

            return ['lote' => $lote, 'creado' => true];
        });
    }

    /** Devuelve true cuando todavía queda trabajo y debe encadenarse otro job. */
    public function processNext(int $loteId): bool
    {
        $lote = SecopConciliacionLote::find($loteId);
        if (!$lote || !$lote->isActive()) {
            return false;
        }

        if ($lote->estado === 'pendiente') {
            $lote->update(['estado' => 'procesando', 'iniciado_at' => now()]);
        }

        $filters = [
            'anio' => 2026,
            'secretaria_id' => $lote->secretaria_id,
            'estado_contrato_id' => $lote->estado_contrato_id,
        ];
        $secretariaIds = array_map('intval', $lote->secretaria_ids ?: $this->alcanceEntidades->configuracionCompleta()['secretaria_ids']);
        $nitEntidades = $lote->nit_entidades ?: $this->alcanceEntidades->configuracionCompleta()['nit_entidades'];
        $personas = $this->eligiblePersonas($filters, (int) $lote->cursor_persona_id, $secretariaIds)
            ->limit(40)
            ->get();
        if ($personas->isEmpty()) {
            $this->finish($lote);
            return false;
        }

        // Una petición por fuente para todo el bloque. Si el proveedor externo
        // rechaza el lote, la ruta individual queda como respaldo seguro.
        $bulkAvailable = false;
        try {
            $byDocument = $this->secop->consultarPorDocumentosVigencia(
                $personas->pluck('cedula_o_nit')->all(),
                2026,
                50000,
                $nitEntidades,
            );
            foreach ($personas as $persona) {
                $documento = preg_replace('/\D+/', '', (string) $persona->cedula_o_nit) ?: '';
                $documento = ltrim($documento, '0') ?: $documento;
                $this->conciliacion->cacheContratosPersonaVigencia(
                    $persona,
                    2026,
                    $nitEntidades,
                    $byDocument[$documento] ?? [],
                );
            }
            $bulkAvailable = true;
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ($personas as $persona) {
            $lote->refresh();
            if (!$lote->isActive()) {
                return false;
            }

            $exact = 0;
            $linked = 0;
            $linkFailures = 0;
            $errors = [];
            $rows = collect();

            try {
                // Con el lote disponible, esta llamada lee caché y no vuelve a SECOP.
                // Si falló la consulta agrupada, conserva el mecanismo individual.
                $result = $this->conciliacion->conciliarPersona(
                    $persona,
                    '2026-01-01',
                    2026,
                    false,
                    2026,
                    $nitEntidades,
                    $secretariaIds,
                );
                if (!($result['consulta_secop_disponible'] ?? true)) {
                    throw new \RuntimeException('SECOP no respondió; la persona quedó sin evaluar completamente.');
                }

                $rows = $result['filas']->filter(fn (array $row) => $this->rowIsEligible($row, $lote, $secretariaIds));
                $exactRows = $rows->filter(fn (array $row) => (bool) ($row['seguro'] ?? false));
                $exact = $exactRows->count();

                foreach ($exactRows as $row) {
                    try {
                        $this->vinculacion->vincularSeguimientoDesdeCandidato(
                            $row['seguimiento']->load('persona'),
                            $row['candidato'],
                            $lote->iniciado_por,
                        );
                        $linked++;
                    } catch (\Throwable $e) {
                        $errors[] = 'Seguimiento #'.$row['seguimiento']->id.': '.$e->getMessage();
                        $linkFailures++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Persona #'.$persona->id.': '.$e->getMessage();
            }

            $results = array_replace($this->emptyResults(), $lote->resultados ?? []);
            foreach ($rows as $row) {
                $key = $this->resultKey((string) ($row['estado'] ?? 'sin_resultado'));
                $results[$key]++;
            }
            $results['vinculos_fallidos'] += $linkFailures;

            $lote->update([
                'cursor_persona_id' => $persona->id,
                'personas_procesadas' => $lote->personas_procesadas + 1,
                'seguimientos_revisados' => $lote->seguimientos_revisados + $rows->count(),
                'coincidencias_exactas' => $lote->coincidencias_exactas + $exact,
                'vinculos_creados' => $lote->vinculos_creados + $linked,
                'resultados' => $results,
                'errores' => $lote->errores + count($errors),
                'ultimo_error' => $errors !== []
                    ? implode(' ', $errors).($bulkAvailable ? '' : ' Consulta masiva no disponible; se usó respaldo individual.')
                    : $lote->ultimo_error,
            ]);
        }

        $lastPersonaId = (int) $personas->last()->id;
        if (!$this->eligiblePersonas($filters, $lastPersonaId, $secretariaIds)->exists()) {
            $this->finish($lote->fresh());
            return false;
        }

        return true;
    }

    public function eligiblePersonas(array $filters, int $cursor = 0, array $secretariaIds = []): Builder
    {
        return Persona::query()
            ->where('id', '>', $cursor)
            ->whereHas('seguimientos', function ($query) use ($filters, $secretariaIds) {
                $query->where('tipo', 'contrato')
                    ->where('anio', 2026)
                    ->where(function ($eligible) {
                        $eligible->where(function ($numbered) {
                            $numbered->whereNotNull('numero_contrato')->where('numero_contrato', '<>', '');
                        })->orWhere(function ($authorized) {
                            $authorized->where('aut_despacho', true)->where('aut_planeacion', true);
                        });
                    })
                    ->whereDoesntHave('vinculoSecop');

                if ($secretariaIds !== []) {
                    $query->whereIn('secretaria_id', array_map('intval', $secretariaIds));
                }

                if (!empty($filters['secretaria_id'])) {
                    $query->where('secretaria_id', (int) $filters['secretaria_id']);
                }
                if (!empty($filters['estado_contrato_id'])) {
                    $query->where('estado_contrato_id', (int) $filters['estado_contrato_id']);
                }
            })
            ->orderBy('id');
    }

    private function rowIsEligible(array $row, SecopConciliacionLote $lote, array $secretariaIds): bool
    {
        $seguimiento = $row['seguimiento'];
        $eligible = filled($seguimiento->numero_contrato)
            || ($seguimiento->aut_despacho && $seguimiento->aut_planeacion);
        if ((int) $seguimiento->anio !== 2026 || !$eligible || $seguimiento->vinculoSecop) {
            return false;
        }
        if (!in_array((int) $seguimiento->secretaria_id, $secretariaIds, true)) return false;
        if ($lote->secretaria_id && (int) $seguimiento->secretaria_id !== (int) $lote->secretaria_id) {
            return false;
        }
        return !$lote->estado_contrato_id
            || (int) $seguimiento->estado_contrato_id === (int) $lote->estado_contrato_id;
    }

    private function resultKey(string $status): string
    {
        return match ($status) {
            'exacto' => 'exactos',
            'aproximado' => 'aproximados',
            'ambiguo' => 'varias_coincidencias',
            'conflicto' => 'contrato_utilizado',
            'requiere_configuracion' => 'entidad_sin_parametrizar',
            'requiere_revision' => 'entidad_o_documento_diferente',
            'no_habilitado' => 'no_habilitados',
            default => 'sin_contrato_encontrado',
        };
    }

    private function emptyResults(): array
    {
        return [
            'exactos' => 0,
            'aproximados' => 0,
            'sin_contrato_encontrado' => 0,
            'varias_coincidencias' => 0,
            'entidad_sin_parametrizar' => 0,
            'entidad_o_documento_diferente' => 0,
            'contrato_utilizado' => 0,
            'no_habilitados' => 0,
            'vinculos_fallidos' => 0,
        ];
    }

    private function finish(SecopConciliacionLote $lote): void
    {
        $lote->update(['estado' => 'finalizado', 'finalizado_at' => now()]);
    }
}
