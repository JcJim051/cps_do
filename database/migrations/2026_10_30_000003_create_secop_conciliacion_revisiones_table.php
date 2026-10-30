<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ejecuta parte del DDL antes de reportar ciertos errores. Si
        // una primera ejecución alcanzó a crear la tabla, completamos solo
        // el índice pendiente y dejamos que Laravel registre la migración.
        if (Schema::hasTable('secop_conciliacion_revisiones')) {
            if (!Schema::hasIndex('secop_conciliacion_revisiones', 'scr_fuente_ident_idx')) {
                Schema::table('secop_conciliacion_revisiones', function (Blueprint $table) {
                    $table->index(['fuente_secop', 'identificador_externo'], 'scr_fuente_ident_idx');
                });
            }
            return;
        }

        Schema::create('secop_conciliacion_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('secop_conciliacion_lotes')->cascadeOnDelete();
            $table->foreignId('seguimiento_id')->constrained('seguimientos')->cascadeOnDelete();
            $table->string('fuente_secop', 20);
            $table->string('identificador_externo');
            $table->string('referencia_contrato')->nullable();
            $table->unsignedSmallInteger('puntaje')->nullable();
            $table->integer('margen')->nullable();
            $table->json('razones')->nullable();
            $table->json('bloqueos')->nullable();
            $table->json('candidato');
            $table->string('estado', 20)->default('pendiente')->index();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['lote_id', 'seguimiento_id'], 'scr_lote_seg_uq');
            $table->index(['lote_id', 'estado'], 'scr_lote_estado_idx');
            $table->index(['fuente_secop', 'identificador_externo'], 'scr_fuente_ident_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_conciliacion_revisiones');
    }
};
