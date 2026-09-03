<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('secop_auditoria_lotes', 'criterio_vigencia')) {
            Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
                $table->string('criterio_vigencia', 40)->default('firma_desde_anio')->after('anio');
            });
        }
        if (!Schema::hasColumn('secop_auditoria_lotes', 'alertas_secop')) {
            Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
                $table->unsignedInteger('alertas_secop')->default(0)->after('coincidencias_exactas');
            });
        }

        if (!Schema::hasColumn('secop_auditoria_hallazgos', 'nombre_entidad')) {
            Schema::table('secop_auditoria_hallazgos', function (Blueprint $table) {
                $table->string('nombre_entidad')->nullable()->after('referencia_contrato');
                $table->index(['lote_id', 'fuente_secop'], 'secop_auditoria_lote_fuente_idx');
                $table->index(['lote_id', 'estado_secop'], 'secop_auditoria_lote_estado_idx');
            });
        }
        if (!Schema::hasColumn('secop_auditoria_hallazgos', 'fecha_firma')) {
            Schema::table('secop_auditoria_hallazgos', function (Blueprint $table) {
                $table->date('fecha_firma')->nullable()->after('estado_secop');
            });
        }

        // Todo lote creado antes de esta migración conserva explícitamente
        // la metodología con la que comenzó y no se mezcla con el nuevo barrido.
        DB::table('secop_auditoria_lotes')->whereNull('criterio_vigencia')->update([
            'criterio_vigencia' => 'firma_desde_anio',
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('secop_auditoria_hallazgos', 'nombre_entidad')) {
            Schema::table('secop_auditoria_hallazgos', function (Blueprint $table) {
                $table->dropIndex('secop_auditoria_lote_fuente_idx');
                $table->dropIndex('secop_auditoria_lote_estado_idx');
                $table->dropColumn('nombre_entidad');
            });
        }
        if (Schema::hasColumn('secop_auditoria_hallazgos', 'fecha_firma')) {
            Schema::table('secop_auditoria_hallazgos', fn (Blueprint $table) => $table->dropColumn('fecha_firma'));
        }
        if (Schema::hasColumn('secop_auditoria_lotes', 'criterio_vigencia')) {
            Schema::table('secop_auditoria_lotes', function (Blueprint $table) {
                $table->dropColumn('criterio_vigencia');
            });
        }
        if (Schema::hasColumn('secop_auditoria_lotes', 'alertas_secop')) {
            Schema::table('secop_auditoria_lotes', fn (Blueprint $table) => $table->dropColumn('alertas_secop'));
        }
    }
};
