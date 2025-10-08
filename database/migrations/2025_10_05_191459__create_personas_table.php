<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_contratista');
            $table->string('cedula_o_nit');
            $table->string('celular')->nullable();
            $table->enum('genero', ['Masculino','Femenino'])->nullable();
            
            // 1. Nuevo campo para la relación a 'estado_personas'
            $table->unsignedBigInteger('estado_persona_id')->nullable(); 
            
            // 2. Nuevo campo para la relación a 'tipo'
            $table->unsignedBigInteger('tipos_id')->nullable(); 

            // Se mantiene el resto de campos...
            $table->unsignedBigInteger('nivel_academico_id')->nullable(); // Relación
            $table->string('foto')->nullable();
            $table->string('documento_pdf')->nullable();
            $table->string('tecnico_tecnologo_profesion')->nullable();
            $table->string('especializacion')->nullable();
            $table->string('maestria')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->unsignedBigInteger('caso_id')->nullable();
            
             $table->timestamps();
            $table->boolean('no_tocar')->default(false);
            $table->unsignedBigInteger('secretaria_id')->nullable();
            $table->unsignedBigInteger('gerencia_id')->nullable();
            
            // Definición de las Relaciones Foráneas
            $table->foreign('nivel_academico_id')->references('id')->on('niveles_academicos')->onDelete('set null');
            $table->foreign('referencia_id')->references('id')->on('referencias')->onDelete('set null');
            $table->foreign('caso_id')->references('id')->on('casos')->onDelete('set null');
            $table->foreign('secretaria_id')->references('id')->on('secretarias')->onDelete('set null');
            $table->foreign('gerencia_id')->references('id')->on('gerencias')->onDelete('set null');
            
            // ¡Nuevas Claves Foráneas!
            $table->foreign('estado_persona_id')->references('id')->on('estado_personas')->onDelete('set null');
            $table->foreign('tipos_id')->references('id')->on('tipos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};