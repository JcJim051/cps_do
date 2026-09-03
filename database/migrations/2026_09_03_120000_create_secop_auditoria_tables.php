<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secop_auditoria_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->default(2026);
            $table->foreignId('iniciado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->unsignedBigInteger('cursor_persona_id')->default(0);
            $table->unsignedInteger('total_personas')->default(0);
            $table->unsignedInteger('personas_procesadas')->default(0);
            $table->unsignedInteger('solo_integra')->default(0);
            $table->unsignedInteger('solo_secop')->default(0);
            $table->unsignedInteger('requiere_revision')->default(0);
            $table->unsignedInteger('coincidencias_exactas')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('secop_auditoria_hallazgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('secop_auditoria_lotes')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('seguimiento_id')->nullable()->constrained('seguimientos')->nullOnDelete();
            $table->string('tipo', 30)->index();
            $table->string('fuente_secop', 20)->nullable();
            $table->string('identificador_externo')->nullable();
            $table->string('referencia_contrato')->nullable();
            $table->string('estado_secop')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('valor_total', 18, 2)->nullable();
            $table->json('detalle')->nullable();
            $table->timestamps();

            $table->index(['lote_id', 'tipo']);
            $table->index(['persona_id', 'seguimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_auditoria_hallazgos');
        Schema::dropIfExists('secop_auditoria_lotes');
    }
};
