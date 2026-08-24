<?php

namespace App\Console\Commands;

use App\Models\SecopVinculo;
use App\Services\SecopVinculacionService;
use Illuminate\Console\Command;

class RefrescarVinculosSecop extends Command
{
    protected $signature = 'secop:refresh-links {--id=}';
    protected $description = 'Actualiza las instantáneas de los contratos y procesos SECOP vinculados';

    public function handle(SecopVinculacionService $service): int
    {
        $query = SecopVinculo::query()->with(['prevalidacion.fuente', 'seguimiento.prevalidacionOrigen.fuente']);
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }
        $ok = $errors = 0;
        $query->orderBy('id')->chunkById(50, function ($links) use ($service, &$ok, &$errors) {
            foreach ($links as $link) {
                try {
                    $service->refrescar($link);
                    $ok++;
                } catch (\Throwable $e) {
                    report($e);
                    $errors++;
                }
            }
        });
        $this->info("Actualizados: {$ok}; errores: {$errors}");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
