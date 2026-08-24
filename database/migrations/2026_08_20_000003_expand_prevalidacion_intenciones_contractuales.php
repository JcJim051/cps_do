<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prevalidacion_fuentes', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio_objetivo')->nullable()->after('fila_encabezados');
        });

        Schema::table('prevalidaciones_contractuales', function (Blueprint $table) {
            $table->string('estado_origen')->nullable()->after('estado');
            $table->string('etapa', 40)->default('POR_GESTIONAR')->after('estado_origen')->index();
            $table->string('numero_origen')->nullable()->after('fila_origen');
            $table->text('nombre_reportado')->nullable()->after('nombre_contratista');
            $table->boolean('nombre_requiere_revision')->default(false)->after('nombre_reportado')->index();
            $table->string('celular', 100)->nullable()->after('programa_origen');
            $table->string('nivel_academico')->nullable()->after('celular');
            $table->text('profesion')->nullable()->after('nivel_academico');
            $table->text('especializacion')->nullable()->after('profesion');
            $table->text('maestria')->nullable()->after('especializacion');
            $table->string('fuente_recursos')->nullable()->after('maestria');
            $table->string('adicion', 50)->nullable()->after('valor_total_planeado');
            $table->date('fecha_inicio_adicion')->nullable()->after('adicion');
            $table->date('fecha_fin_adicion')->nullable()->after('fecha_inicio_adicion');
            $table->integer('tiempo_adicion_dias')->nullable()->after('fecha_fin_adicion');
            $table->integer('tiempo_total_dias')->nullable()->after('tiempo_adicion_dias');
            $table->decimal('valor_adicion', 15, 2)->nullable()->after('tiempo_total_dias');
            $table->decimal('valor_total_contrato', 15, 2)->nullable()->after('valor_adicion');
            $table->string('evaluacion')->nullable()->after('valor_total_contrato');
            $table->string('continua', 50)->nullable()->after('evaluacion');
            $table->text('sector')->nullable()->after('observaciones');
            $table->text('enlace_origen')->nullable()->after('sector');
        });
    }

    public function down(): void
    {
        Schema::table('prevalidaciones_contractuales', function (Blueprint $table) {
            $table->dropIndex(['etapa']);
            $table->dropIndex(['nombre_requiere_revision']);
            $table->dropColumn([
                'estado_origen', 'etapa', 'numero_origen', 'nombre_reportado', 'nombre_requiere_revision',
                'celular', 'nivel_academico', 'profesion', 'especializacion', 'maestria', 'fuente_recursos',
                'adicion', 'fecha_inicio_adicion', 'fecha_fin_adicion', 'tiempo_adicion_dias', 'tiempo_total_dias',
                'valor_adicion', 'valor_total_contrato', 'evaluacion', 'continua', 'sector', 'enlace_origen',
            ]);
        });
        Schema::table('prevalidacion_fuentes', fn (Blueprint $table) => $table->dropColumn('anio_objetivo'));
    }
};
