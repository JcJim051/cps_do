<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_referencia', function (Blueprint $table) {
            // Claves foráneas sin autoincremento
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('referencia_id');

            // Definir las relaciones
            $table->foreign('persona_id')->references('id')->on('personas')->onDelete('cascade');
            $table->foreign('referencia_id')->references('id')->on('referencias')->onDelete('cascade');

            // Definir la clave primaria compuesta (para evitar duplicados)
            $table->primary(['persona_id', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_referencia');
    }
};
