<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Asignar desde la campaña de ingreso de la persona
        DB::table('seguimientos')
            ->whereNull('ejercicio_politico_id')
            ->join('personas', 'personas.id', '=', 'seguimientos.persona_id')
            ->whereNotNull('personas.ejercicio_politico_origen_id')
            ->update(['seguimientos.ejercicio_politico_id' => DB::raw('personas.ejercicio_politico_origen_id')]);

        // 2) Si aún hay nulos, asignar campaña inicial si existe
        $campaign = DB::table('ejercicios_politicos')
            ->where('nombre', 'Gobernacion 2023')
            ->first();

        if ($campaign) {
            DB::table('seguimientos')
                ->whereNull('ejercicio_politico_id')
                ->update(['ejercicio_politico_id' => $campaign->id]);
        }
    }

    public function down(): void
    {
        // No revertimos para no perder trazabilidad.
    }
};
