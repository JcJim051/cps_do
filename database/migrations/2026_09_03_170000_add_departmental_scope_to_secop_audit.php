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
            $table->boolean('auditoria_secop_incluida')->default(false)->after('nombre_secop')->index();
        });

        // Integra es departamental: las dependencias existentes quedan incluidas,
        // salvo entidades conocidas que no pertenecen al orden departamental.
        DB::table('secretarias')->update(['auditoria_secop_incluida' => true]);
        DB::table('secretarias')
            ->whereIn('nombre', ['CORMACARENA', 'UNILLANOS', 'CASABE'])
            ->update(['auditoria_secop_incluida' => false]);

        // Dependencias de la administración central comparten el NIT del
        // Departamento del Meta. Las descentralizadas conservan su propio NIT.
        DB::table('secretarias')
            ->where(function ($query) {
                $query->where('nombre', 'like', 'SECRETARÍA%')
                    ->orWhere('nombre', 'like', 'DIRECCIÓN%')
                    ->orWhere('nombre', 'like', 'OFICINA%')
                    ->orWhereIn('nombre', ['DEPARTAMENTO ADMINISTRATIVO DE PLANEACIÓN', 'UNIDAD DE LICORES']);
            })
            ->whereNull('nit_secop')
            ->update(['nit_secop' => '8920001488', 'nombre_secop' => 'DEPARTAMENTO DEL META']);

        Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
            $table->string('alcance_entidad', 50)->default('sin_filtro_entidad')->after('criterio_vigencia');
            $table->json('secretaria_ids')->nullable()->after('persona_ids');
            $table->json('nit_entidades')->nullable()->after('secretaria_ids');
        });

        // Un lote iniciado con la metodología anterior no puede continuar y
        // producir cifras mezcladas después del despliegue de este alcance.
        DB::table('secop_auditoria_lotes')
            ->whereIn('estado', ['pendiente', 'procesando'])
            ->update([
                'estado' => 'fallido',
                'ultimo_error' => 'Lote detenido al activar el alcance departamental. Inicia una auditoría nueva.',
                'finalizado_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
            $table->dropColumn(['alcance_entidad', 'secretaria_ids', 'nit_entidades']);
        });
        Schema::table('secretarias', function (Blueprint $table) {
            $table->dropIndex(['auditoria_secop_incluida']);
            $table->dropColumn('auditoria_secop_incluida');
        });
    }
};
