<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretarias', function (Blueprint $table) {
            $table->string('nit_secop', 30)->nullable()->after('convencion');
            $table->string('nombre_secop')->nullable()->after('nit_secop');
        });

        if (Schema::hasTable('prevalidacion_fuentes')) {
            DB::table('prevalidacion_fuentes')
                ->whereNotNull('secretaria_id')
                ->whereNotNull('nit_entidad')
                ->orderBy('id')
                ->get(['secretaria_id', 'nit_entidad'])
                ->each(function ($fuente) {
                    DB::table('secretarias')
                        ->where('id', $fuente->secretaria_id)
                        ->whereNull('nit_secop')
                        ->update(['nit_secop' => $fuente->nit_entidad]);
                });
        }

        // Valores conocidos del piloto. Se pueden ajustar desde la administración de secretarías.
        DB::table('secretarias')
            ->where('nombre', 'AIM')
            ->update([
                'nit_secop' => '9002205475',
                'nombre_secop' => 'AGENCIA PARA LA INFRAESTRUCTURA DEL META',
            ]);

        DB::table('secretarias')
            ->where('nombre', 'DIRECCIÓN PARA LA GESTIÓN DEL RIESGO DE DESASTRES')
            ->update([
                'nit_secop' => '8920001488',
                'nombre_secop' => 'DEPARTAMENTO DEL META',
            ]);
    }

    public function down(): void
    {
        Schema::table('secretarias', function (Blueprint $table) {
            $table->dropColumn(['nit_secop', 'nombre_secop']);
        });
    }
};
