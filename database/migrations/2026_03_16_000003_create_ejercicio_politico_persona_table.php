<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejercicio_politico_persona', function (Blueprint $table) {
            $table->unsignedBigInteger('ejercicio_politico_id');
            $table->unsignedBigInteger('persona_id');

            $table->foreign('ejercicio_politico_id')
                ->references('id')
                ->on('ejercicios_politicos')
                ->onDelete('cascade');

            $table->foreign('persona_id')
                ->references('id')
                ->on('personas')
                ->onDelete('cascade');

            $table->primary(['ejercicio_politico_id', 'persona_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicio_politico_persona');
    }
};
