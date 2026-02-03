<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->string('operador');
            $table->string('municipio');
            $table->string('institucion');
            $table->string('sede')->nullable();
            $table->string('nombre_apellidos');
            $table->string('cedula')->unique();
            $table->string('contacto')->nullable();
            $table->string('ref_1')->nullable();
            $table->string('ref_2')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};
