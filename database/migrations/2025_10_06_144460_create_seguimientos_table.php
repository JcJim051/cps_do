<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();

            // Relación con personas
            $table->unsignedBigInteger('persona_id');
            $table->foreign('persona_id')
                  ->references('id')->on('personas')
                  ->onDelete('cascade');

            // Tipo seguimiento
            $table->enum('tipo', ['entrevista', 'contrato']);

            // Relaciones a catálogos
            $table->unsignedBigInteger('evaluacion_id')->nullable();
            $table->foreign('evaluacion_id')->references('id')->on('evaluaciones');

            $table->unsignedBigInteger('nivel_academico_id')->nullable();
            $table->foreign('nivel_academico_id')->references('id')->on('niveles_academicos');

            $table->unsignedBigInteger('estado_id')->nullable();
            $table->foreign('estado_id')->references('id')->on('estados');

            $table->unsignedBigInteger('estado_contrato_id')->nullable();
            $table->foreign('estado_contrato_id')->references('id')->on('estados');

            $table->unsignedBigInteger('secretaria_id')->nullable();
            $table->foreign('secretaria_id')->references('id')->on('secretarias');

            $table->unsignedBigInteger('gerencia_id')->nullable();
            $table->foreign('gerencia_id')->references('id')->on('gerencias');

            $table->unsignedBigInteger('fuente_id')->nullable();
            $table->foreign('fuente_id')->references('id')->on('fuentes');

            $table->text('observaciones')->nullable();

            // ---- ENTREVISTA ----
            $table->date('fecha_entrevista')->nullable();

            // ---- CONTRATO ----
            $table->year('anio')->nullable();
            $table->string('numero_contrato')->nullable();
            $table->date('fecha_acta_inicio')->nullable();
            $table->date('fecha_finalizacion')->nullable();
            $table->integer('tiempo_ejecucion_dias')->nullable();
            $table->decimal('valor_mensual', 15, 2)->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            

            // Autorizaciones + fechas de aprobación
            $table->boolean('aut_despacho')->default(false);
            $table->date('fecha_aut_despacho')->nullable();

            $table->boolean('aut_planeacion')->default(false);
            $table->date('fecha_aut_planeacion')->nullable();

            $table->boolean('aut_administrativa')->default(false);
            $table->date('fecha_aut_administrativa')->nullable();

            // Adiciones
            $table->enum('adicion', ['SI', 'NO'])->nullable();
            $table->date('fecha_acta_inicio_adicion')->nullable();
            $table->date('fecha_finalizacion_adicion')->nullable();
            $table->integer('tiempo_ejecucion_dias_adicion')->nullable();
            $table->integer('tiempo_total_ejecucion_dias')->nullable();
            $table->decimal('valor_adicion', 15, 2)->nullable();
            $table->decimal('valor_total_contrato', 15, 2)->nullable();

            // Observaciones contrato
            $table->text('observaciones_contrato')->nullable();
            $table->enum('continua', ['SI', 'NO'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};
