<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secop_conciliacion_lotes', function (Blueprint $table) {
            $table->json('secretaria_ids')->nullable()->after('estado_contrato_id');
            $table->json('nit_entidades')->nullable()->after('secretaria_ids');
            $table->unsignedInteger('seguimientos_revisados')->default(0)->after('personas_procesadas');
            $table->json('resultados')->nullable()->after('vinculos_creados');
        });
    }

    public function down(): void
    {
        Schema::table('secop_conciliacion_lotes', function (Blueprint $table) {
            $table->dropColumn(['secretaria_ids', 'nit_entidades', 'seguimientos_revisados', 'resultados']);
        });
    }
};
