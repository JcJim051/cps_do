<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $campaignName = 'Gobernacion 2023';

        $campaign = DB::table('ejercicios_politicos')
            ->where('nombre', $campaignName)
            ->first();

        if (!$campaign) {
            $campaignId = DB::table('ejercicios_politicos')->insertGetId([
                'nombre' => $campaignName,
                'descripcion' => 'Campaña inicial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $campaignId = $campaign->id;
        }

        DB::table('personas')->orderBy('id')->chunkById(1000, function ($personas) use ($campaignId) {
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
        });

        DB::table('personas')
            ->whereNull('ejercicio_politico_origen_id')
            ->update(['ejercicio_politico_origen_id' => $campaignId]);
    }

    public function down(): void
    {
        $campaignName = 'Gobernacion 2023';

        $campaign = DB::table('ejercicios_politicos')
            ->where('nombre', $campaignName)
            ->first();

        if ($campaign) {
            DB::table('personas')
                ->where('ejercicio_politico_origen_id', $campaign->id)
                ->update(['ejercicio_politico_origen_id' => null]);
            DB::table('ejercicios_politicos')->where('id', $campaign->id)->delete();
        }
    }
};
