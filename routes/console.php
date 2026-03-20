<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('campaign:attach-personas {campaignId : ID de la campaña} {--ref= : ID de referencia para filtrar} {--all : Asociar todas las personas} {--set-origin : Si está vacío, asigna campaña como origen} {--dry-run : Solo muestra el total sin escribir}', function () {
    $campaignId = (int) $this->argument('campaignId');
    $refId = $this->option('ref');
    $all = (bool) $this->option('all');
    $setOrigin = (bool) $this->option('set-origin');
    $dryRun = (bool) $this->option('dry-run');

    if (!$all && !$refId) {
        $this->error('Debes usar --all o --ref=ID.');
        return self::FAILURE;
    }

    $campaign = DB::table('ejercicios_politicos')->where('id', $campaignId)->first();
    if (!$campaign) {
        $this->error('La campaña no existe.');
        return self::FAILURE;
    }

    $query = DB::table('personas')->select('personas.id');

    if ($refId) {
        $query->where(function ($q) use ($refId) {
            $q->where('referencia_id', $refId)
              ->orWhereExists(function ($sub) use ($refId) {
                  $sub->select(DB::raw(1))
                      ->from('persona_referencia')
                      ->whereColumn('persona_referencia.persona_id', 'personas.id')
                      ->where('persona_referencia.referencia_id', $refId);
              });
        });
    }

    $total = $query->count();
    $this->info("Personas encontradas: {$total}");

    if ($dryRun) {
        $this->info('Dry-run activado. No se realizaron cambios.');
        return self::SUCCESS;
    }

    $query->orderBy('id')->chunkById(1000, function ($personas) use ($campaignId, $setOrigin) {
        $rows = [];
        foreach ($personas as $persona) {
            $rows[] = [
                'ejercicio_politico_id' => $campaignId,
                'persona_id' => $persona->id,
            ];
        }

        if (!empty($rows)) {
            DB::table('ejercicio_politico_persona')->insertOrIgnore($rows);
        }

        if ($setOrigin) {
            DB::table('personas')
                ->whereIn('id', collect($personas)->pluck('id')->all())
                ->whereNull('ejercicio_politico_origen_id')
                ->update(['ejercicio_politico_origen_id' => $campaignId]);
        }
    });

    $this->info('Proceso finalizado correctamente.');
    return self::SUCCESS;
})->purpose('Asocia personas a campañas de forma masiva y segura');
