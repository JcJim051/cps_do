<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->unique(['lote_id', 'seguimiento_id']);
            $table->index(['lote_id', 'estado']);
            $table->index(['fuente_secop', 'identificador_externo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_conciliacion_revisiones');
    }
};
