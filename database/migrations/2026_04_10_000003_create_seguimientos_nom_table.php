<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos_nom', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('secretaria_id');
            $table->unsignedBigInteger('cargo_id');
            $table->unsignedBigInteger('tipo_vinculacion_id');
            $table->date('fecha_ingreso');
            $table->decimal('salario', 15, 2);
            $table->timestamps();

            $table->foreign('persona_id')->references('id')->on('personas')->onDelete('cascade');
            $table->foreign('secretaria_id')->references('id')->on('secretarias')->onDelete('restrict');
            $table->foreign('cargo_id')->references('id')->on('cargos')->onDelete('restrict');
            $table->foreign('tipo_vinculacion_id')->references('id')->on('tipos_vinculacion')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos_nom');
    }
};
