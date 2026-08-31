<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secop_conciliacion_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->default(2026);
            $table->foreignId('secretaria_id')->nullable()->constrained('secretarias')->nullOnDelete();
            $table->foreignId('estado_contrato_id')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('iniciado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->unsignedBigInteger('cursor_persona_id')->default(0);
            $table->unsignedInteger('total_personas')->default(0);
            $table->unsignedInteger('personas_procesadas')->default(0);
            $table->unsignedInteger('coincidencias_exactas')->default(0);
            $table->unsignedInteger('vinculos_creados')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secop_conciliacion_lotes');
    }
};
