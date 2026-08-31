<?php

namespace App\Console\Commands;

use App\Models\SecopVinculo;
use App\Services\SecopVinculacionService;
use App\Services\SecopSincronizacionMasivaService;
use Illuminate\Console\Command;

class RefrescarVinculosSecop extends Command
{
    protected $signature = 'secop:refresh-links {--id= : ID del vínculo} {--year=2026 : Vigencia} {--dry-run : Simula sin modificar seguimientos}';
    protected $description = 'Consulta SECOP por lotes y sincroniza los Seguimientos vinculados';

    public function handle(SecopSincronizacionMasivaService $service): int
    {
        $query = SecopVinculo::query()
            ->whereNotNull('seguimiento_id')
            ->where('sincronizacion_automatica', true)
            ->with(['seguimiento', 'ultimaInstantanea']);
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }
        if ($this->option('year')) $query->whereHas('seguimiento', fn ($q) => $q->where('anio', $this->option('year')));
        $summary = $service->run($query->get(), $this->option('dry-run') ? 'simulacion' : 'automatico', null, (bool) $this->option('dry-run'));
        $this->table(['Total', 'Actualizados', 'Sin cambios', 'Excluidos', 'Sin datos', 'Estados desconocidos', 'Errores'], [[
            $summary['total'], $summary['actualizados'], $summary['sin_cambios'], $summary['excluidos'],
            $summary['sin_datos'], $summary['estados_desconocidos'], $summary['errores'],
        ]]);
        return $summary['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
