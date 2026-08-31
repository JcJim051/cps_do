<?php

namespace App\Services;

use App\Models\SecopVinculo;
use Illuminate\Database\Eloquent\Collection;

class SecopSincronizacionMasivaService
{
    public function __construct(
        private DatosAbiertosSecopService $secop,
        private SecopVinculacionService $vinculacion,
        private SecopAplicacionService $aplicacion,
    ) {
    }

    public function run(Collection $links, string $mode, ?int $userId = null, bool $dryRun = false): array
    {
        $summary = ['total' => $links->count(), 'actualizados' => 0, 'sin_cambios' => 0, 'excluidos' => 0, 'sin_datos' => 0, 'estados_desconocidos' => 0, 'errores' => 0, 'filas' => []];
        foreach ($links->groupBy('fuente_secop') as $source => $sourceLinks) {
            try {
                $batch = $this->secop->consultarPorIdentificadores($source, $sourceLinks->pluck('identificador_externo')->all());
            } catch (\Throwable $e) {
                $batch = [];
                foreach ($sourceLinks as $link) $this->error($summary, $link, $e);
                continue;
            }

            foreach ($sourceLinks as $link) {
                try {
                    $candidate = $batch[$link->identificador_externo] ?? null;
                    // Compatibilidad con vínculos SECOP I anteriores al UID.
                    if (!$candidate && $source === 'secop1' && str_contains($link->identificador_externo, '|')) {
                        $result = $this->vinculacion->sincronizar($link, $mode, $userId, $dryRun);
                    } elseif (!$candidate) {
                        $result = ['resultado' => 'sin_datos', 'cambios' => [], 'omitidos' => [], 'alertas' => ['Contrato no retornado por SECOP.']];
                    } else {
                        $this->vinculacion->snapshot($link, $candidate);
                        $link->forceFill(['ultima_consulta_at' => now()])->save();
                        $result = $this->aplicacion->apply($link->fresh(['seguimiento', 'ultimaInstantanea']), $mode, $userId, $dryRun);
                    }
                    $this->count($summary, $link, $result);
                } catch (\Throwable $e) {
                    $this->error($summary, $link, $e);
                }
            }
        }
        return $summary;
    }

    private function count(array &$summary, SecopVinculo $link, array $result): void
    {
        $key = match ($result['resultado'] ?? 'sin_datos') {
            'actualizado', 'actualizable' => 'actualizados',
            'sin_cambios' => 'sin_cambios',
            'excluido' => 'excluidos',
            default => 'sin_datos',
        };
        $summary[$key]++;
        if (collect($result['alertas'] ?? [])->contains(fn ($a) => str_contains($a, 'sin mapeo'))) $summary['estados_desconocidos']++;
        $summary['filas'][] = ['vinculo_id' => $link->id, 'seguimiento_id' => $link->seguimiento_id, 'resultado' => $result['resultado'] ?? 'sin_datos', 'cambios' => $result['cambios'] ?? [], 'omitidos' => $result['omitidos'] ?? [], 'alertas' => $result['alertas'] ?? []];
    }

    private function error(array &$summary, SecopVinculo $link, \Throwable $e): void
    {
        $summary['errores']++;
        $link->forceFill(['ultimo_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
        $summary['filas'][] = ['vinculo_id' => $link->id, 'seguimiento_id' => $link->seguimiento_id, 'resultado' => 'error', 'cambios' => [], 'omitidos' => [], 'alertas' => [$e->getMessage()]];
        report($e);
    }
}
