<?php

namespace App\Services;

use App\Jobs\ProcesarVinculacionSecopExacta;
use App\Models\Persona;
use App\Models\SecopConciliacionLote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class SecopVinculacionMasivaService
{
    public function __construct(
        private SecopConciliacionService $conciliacion,
        private SecopVinculacionService $vinculacion,
    ) {
    }

    /** @return array{lote: SecopConciliacionLote, creado: bool} */
    public function start(array $filters, ?int $userId): array
    {
        return Cache::lock('secop-vinculacion-exacta-inicio', 10)->block(3, function () use ($filters, $userId) {
            $active = SecopConciliacionLote::query()
                ->whereIn('estado', ['pendiente', 'procesando'])
                ->latest('id')
                ->first();
            if ($active) {
                return ['lote' => $active, 'creado' => false];
            }

            $filters['anio'] = 2026;
            $total = $this->eligiblePersonas($filters)->count();
            $lote = SecopConciliacionLote::create([
                'anio' => 2026,
                'secretaria_id' => $filters['secretaria_id'] ?? null,
                'estado_contrato_id' => $filters['estado_contrato_id'] ?? null,
                'iniciado_por' => $userId,
                'estado' => $total > 0 ? 'pendiente' : 'finalizado',
                'total_personas' => $total,
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
        $persona = $this->eligiblePersonas($filters, (int) $lote->cursor_persona_id)->first();
        if (!$persona) {
            $this->finish($lote);
            return false;
        }

        $exact = 0;
        $linked = 0;
        $errors = [];

        try {
            // La búsqueda alternativa por otra cédula solo informa revisiones manuales;
            // no puede producir coincidencias exactas y se omite en el proceso masivo.
            $result = $this->conciliacion->conciliarPersona($persona, '2026-01-01', 2026, false);
            $rows = $result['filas']->filter(fn (array $row) => $this->rowMatches($row, $lote));
            $exact = $rows->count();

            foreach ($rows as $row) {
                try {
                    $this->vinculacion->vincularSeguimientoDesdeCandidato(
                        $row['seguimiento']->load('persona'),
                        $row['candidato'],
                        $lote->iniciado_por,
                    );
                    $linked++;
                } catch (\Throwable $e) {
                    $errors[] = 'Seguimiento #'.$row['seguimiento']->id.': '.$e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Persona #'.$persona->id.': '.$e->getMessage();
        }

        $lote->update([
            'cursor_persona_id' => $persona->id,
            'personas_procesadas' => $lote->personas_procesadas + 1,
            'coincidencias_exactas' => $lote->coincidencias_exactas + $exact,
            'vinculos_creados' => $lote->vinculos_creados + $linked,
            'errores' => $lote->errores + count($errors),
            'ultimo_error' => $errors !== [] ? implode(' ', $errors) : $lote->ultimo_error,
        ]);

        if (!$this->eligiblePersonas($filters, $persona->id)->exists()) {
            $this->finish($lote->fresh());
            return false;
        }

        return true;
    }

    public function eligiblePersonas(array $filters, int $cursor = 0): Builder
    {
        return Persona::query()
            ->where('id', '>', $cursor)
            ->whereHas('seguimientos', function ($query) use ($filters) {
                $query->where('tipo', 'contrato')
                    ->where('anio', 2026)
                    ->whereNotNull('numero_contrato')
                    ->where('numero_contrato', '<>', '')
                    ->whereDoesntHave('vinculoSecop');

                if (!empty($filters['secretaria_id'])) {
                    $query->where('secretaria_id', (int) $filters['secretaria_id']);
                }
                if (!empty($filters['estado_contrato_id'])) {
                    $query->where('estado_contrato_id', (int) $filters['estado_contrato_id']);
                }
            })
            ->orderBy('id');
    }

    private function rowMatches(array $row, SecopConciliacionLote $lote): bool
    {
        $seguimiento = $row['seguimiento'];
        if (!$row['seguro'] || (int) $seguimiento->anio !== 2026 || $seguimiento->vinculoSecop) {
            return false;
        }
        if ($lote->secretaria_id && (int) $seguimiento->secretaria_id !== (int) $lote->secretaria_id) {
            return false;
        }
        return !$lote->estado_contrato_id
            || (int) $seguimiento->estado_contrato_id === (int) $lote->estado_contrato_id;
    }

    private function finish(SecopConciliacionLote $lote): void
    {
        $lote->update(['estado' => 'finalizado', 'finalizado_at' => now()]);
    }
}
