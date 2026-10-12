<?php

namespace App\Console\Commands;

use App\Models\ProgramaFuente;
use App\Services\ProgramasGoogleSyncService;
use Illuminate\Console\Command;

class SincronizarProgramasGoogle extends Command
{
    protected $signature = 'programas:sync-google {--source= : ID de la fuente} {--dry-run : Valida sin escribir en Google Sheets ni en la base de datos}';

    protected $description = 'Sincroniza las asignaciones de Programas desde Google Sheets';

    public function handle(ProgramasGoogleSyncService $service): int
    {
        $sources = ProgramaFuente::query()
            ->where('activa', true)
            ->when($this->option('source'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No hay fuentes activas para sincronizar.');
            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            $this->info("Sincronizando: {$source->nombre}");
            try {
                $result = $service->sincronizar($source, (bool) $this->option('dry-run'));
                $this->table(
                    ['Leídas', 'Válidas', 'Creadas', 'Actualizadas', 'Inactivadas', 'IDs nuevos', 'Omitidas', 'Errores'],
                    [[
                        $result['filas_leidas'], $result['validas'], $result['creados'],
                        $result['actualizados'], $result['inactivados'], $result['ids_generados'],
                        $result['omitidos'], count($result['errores']),
                    ]]
                );
                foreach (array_slice($result['errores'], 0, 20) as $error) {
                    $this->warn($error);
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
