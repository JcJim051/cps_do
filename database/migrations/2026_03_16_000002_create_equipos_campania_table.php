<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos_campania', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ejercicio_politico_id');
            $table->unsignedBigInteger('coordinador_user_id');
            $table->string('nombre');
            $table->timestamps();

            $table->foreign('ejercicio_politico_id')
                ->references('id')
                ->on('ejercicios_politicos')
                ->onDelete('cascade');

            $table->foreign('coordinador_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos_campania');
    }
};
