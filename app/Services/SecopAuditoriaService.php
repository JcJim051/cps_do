<?php

namespace App\Services;

use App\Jobs\ProcesarAuditoriaSecop;
use App\Models\Persona;
use App\Models\SecopAuditoriaHallazgo;
use App\Models\SecopAuditoriaLote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecopAuditoriaService
{
    public function __construct(
        private SecopConciliacionService $conciliacion,
        private SecopVigenciaService $vigencias,
        private ?SecopAlcanceEntidadService $alcanceEntidades = null,
    ) {}

    /** @return array{lote:SecopAuditoriaLote,creado:bool} */
    public function start(?int $userId, ?string $personaSearch = null): array
    {
        $personaSearch = trim((string) $personaSearch);

        return Cache::lock('secop-auditoria-2026-inicio', 10)->block(3, function () use ($userId, $personaSearch) {
            $active = SecopAuditoriaLote::query()->whereIn('estado', ['pendiente', 'procesando'])->latest('id')->first();
            if ($active) return ['lote' => $active, 'creado' => false];

            $scope = ($this->alcanceEntidades ?? new SecopAlcanceEntidadService())->configuracionCompleta();
            $personaIds = $personaSearch !== '' ? $this->resolvePersonas($personaSearch) : null;
            $total = $personaIds === null ? $this->eligiblePersonas()->count() : count($personaIds);
            $lote = SecopAuditoriaLote::create([
                'anio' => 2026,
                'criterio_vigencia' => 'ejecucion_superpuesta',
                'alcance_entidad' => 'departamental_parametrizado',
                'filtro_persona' => $personaSearch !== '' ? $personaSearch : null,
                'persona_ids' => $personaIds,
                'secretaria_ids' => $scope['secretaria_ids'],
                'nit_entidades' => $scope['nit_entidades'],
                'iniciado_por' => $userId,
                'estado' => $total ? 'pendiente' : 'finalizado',
                'total_personas' => $total,
                'finalizado_at' => $total ? null : now(),
            ]);
            if ($total) ProcesarAuditoriaSecop::dispatch($lote->id);

            return ['lote' => $lote, 'creado' => true];
        });
    }

    public function processNext(int $loteId): bool
    {
        $lote = SecopAuditoriaLote::find($loteId);
        if (!$lote || !$lote->isActive()) return false;
        if ($lote->estado === 'pendiente') $lote->update(['estado' => 'procesando', 'iniciado_at' => now()]);

        $persona = $this->personasDelLote($lote, (int) $lote->cursor_persona_id)->first();
        if (!$persona) return $this->finish($lote);

        $counts = [
            'solo_integra' => 0,
            'solo_secop' => 0,
            'requiere_revision' => 0,
            'coincidencias_exactas' => 0,
            'alertas_secop' => 0,
            'errores' => 0,
        ];
        $lastError = null;
        try {
            $result = $lote->criterio_vigencia === 'ejecucion_superpuesta'
                ? $this->conciliacion->conciliarPersona(
                    $persona,
                    '2026-01-01',
                    null,
                    false,
                    (int) $lote->anio,
                    $lote->nit_entidades ?? [],
                    $lote->secretaria_ids ?? [],
                )
                : $this->conciliacion->conciliarPersona($persona, '2026-01-01', (int) $lote->anio, false);
            if (!($result['consulta_secop_disponible'] ?? true)) {
                throw new \RuntimeException('SECOP no respondió; se omitió la persona para evitar conclusiones incompletas.');
            }

            // El lote pudo ser detenido mientras esperaba la respuesta externa.
            // En ese caso no almacenamos hallazgos ni avanzamos sus contadores.
            $lote->refresh();
            if (!$lote->isActive()) {
                return false;
            }

            DB::transaction(function () use ($lote, $persona, $result, &$counts) {
                foreach ($result['filas'] as $row) {
                    $type = match ($row['estado']) {
                        'exacto' => 'coincidencia_exacta',
                        'ambiguo', 'conflicto', 'requiere_revision', 'requiere_configuracion', 'documento_diferente' => 'requiere_revision',
                        'sin_resultado' => 'solo_integra',
                        'no_habilitado' => $row['candidato'] ? 'requiere_revision' : 'solo_integra',
                        default => null,
                    };
                    if (!$type) continue;
                    $this->storeFinding($lote, $persona, $type, $row['seguimiento'], $row['candidato'], $row);
                    $counts[$type === 'coincidencia_exacta' ? 'coincidencias_exactas' : $type]++;
                }
                foreach ($result['contratos_sin_seguimiento'] as $contract) {
                    $type = $lote->criterio_vigencia === 'ejecucion_superpuesta'
                        ? $this->vigencias->tipoHallazgoContrato($contract['estado'] ?? null)
                        : 'solo_secop';
                    $reason = $type === 'solo_secop'
                        ? 'No existe número y vigencia equivalentes en Seguimientos'
                        : 'Estado preliminar, cancelado o no reconocido; no se contabiliza como contrato faltante';
                    $this->storeFinding($lote, $persona, $type, null, $contract, ['razones' => [$reason]]);
                    $counts[$type === 'alerta_secop' ? 'alertas_secop' : 'solo_secop']++;
                }
            });
        } catch (\Throwable $e) {
            // En una auditoría puntual no debemos consumir la única persona y
            // presentar ceros como un resultado válido. Dejamos que el Job
            // reintente la misma consulta y, si agota sus intentos, marque el
            // lote como inconcluso sin avanzar el cursor.
            if ($lote->filtro_persona) {
                throw $e;
            }

            $counts['errores'] = 1;
            $lastError = 'Persona #'.$persona->id.': '.$e->getMessage();
        }

        $lote->update([
            'cursor_persona_id' => $persona->id,
            'personas_procesadas' => $lote->personas_procesadas + 1,
            'solo_integra' => $lote->solo_integra + $counts['solo_integra'],
            'solo_secop' => $lote->solo_secop + $counts['solo_secop'],
            'requiere_revision' => $lote->requiere_revision + $counts['requiere_revision'],
            'coincidencias_exactas' => $lote->coincidencias_exactas + $counts['coincidencias_exactas'],
            'alertas_secop' => $lote->alertas_secop + $counts['alertas_secop'],
            'errores' => $lote->errores + $counts['errores'],
            'ultimo_error' => $lastError ?: $lote->ultimo_error,
        ]);

        if (!$this->personasDelLote($lote, (int) $persona->id)->exists()) return $this->finish($lote->fresh());
        return true;
    }

    public function eligiblePersonas(int $cursor = 0): Builder
    {
        return Persona::query()
            ->where('id', '>', $cursor)
            ->whereNotNull('cedula_o_nit')
            ->where('cedula_o_nit', '<>', '')
            ->orderBy('id');
    }

    private function personasDelLote(SecopAuditoriaLote $lote, int $cursor = 0): Builder
    {
        $query = $this->eligiblePersonas($cursor);
        $personaIds = array_values(array_filter(array_map('intval', $lote->persona_ids ?? [])));

        return $personaIds !== [] ? $query->whereIn('id', $personaIds) : $query;
    }

    /** @return array<int,int> */
    private function resolvePersonas(string $search): array
    {
        $exactDocument = Persona::query()
            ->where('cedula_o_nit', $search)
            ->whereNotNull('cedula_o_nit')
            ->pluck('id');

        if ($exactDocument->isNotEmpty()) {
            return $exactDocument->map(fn ($id) => (int) $id)->all();
        }

        $matches = Persona::query()
            ->whereNotNull('cedula_o_nit')
            ->where('cedula_o_nit', '<>', '')
            ->where(function (Builder $query) use ($search) {
                $query->where('nombre_contratista', 'like', '%'.$search.'%')
                    ->orWhere('cedula_o_nit', 'like', '%'.$search.'%');
            })
            ->orderBy('id')
            ->limit(26)
            ->pluck('id');

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'persona' => 'No se encontró ninguna persona por ese nombre o cédula.',
            ]);
        }

        if ($matches->count() > 25) {
            throw ValidationException::withMessages([
                'persona' => 'La búsqueda coincide con más de 25 personas. Escribe un nombre más completo o la cédula exacta.',
            ]);
        }

        return $matches->map(fn ($id) => (int) $id)->all();
    }

    private function storeFinding(SecopAuditoriaLote $lote, Persona $persona, string $type, $tracking, ?array $candidate, array $detail): void
    {
        SecopAuditoriaHallazgo::create([
            'lote_id' => $lote->id,
            'persona_id' => $persona->id,
            'seguimiento_id' => $tracking?->id,
            'tipo' => $type,
            'fuente_secop' => $candidate['fuente_codigo'] ?? null,
            'identificador_externo' => $candidate['identificador_externo'] ?? null,
            'referencia_contrato' => $candidate['referencia_contrato'] ?? $tracking?->numero_contrato,
            'nombre_entidad' => $candidate['nombre_entidad'] ?? $tracking?->secretaria?->nombre,
            'estado_secop' => $candidate['estado'] ?? $candidate['estado_contrato'] ?? null,
            'fecha_firma' => $candidate['fecha_firma'] ?? null,
            'fecha_inicio' => $candidate['fecha_inicio'] ?? null,
            'fecha_fin' => $candidate['fecha_fin'] ?? null,
            'valor_total' => $candidate['valor_total_con_adiciones'] ?? $candidate['valor_total'] ?? null,
            'detalle' => ['estado' => $detail['estado'] ?? null, 'etiqueta' => $detail['estado_label'] ?? null, 'razones' => $detail['razones'] ?? []],
        ]);
    }

    private function finish(SecopAuditoriaLote $lote): bool
    {
        $lote->update(['estado' => 'finalizado', 'finalizado_at' => now()]);
        return false;
    }
}
