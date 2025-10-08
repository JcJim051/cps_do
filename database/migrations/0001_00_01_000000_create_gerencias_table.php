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
        Schema::create('gerencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secretaria_id')->constrained('secretarias')->onDelete('cascade');
            $table->string('nombre');       // Nombre completo
            $table->string('convencion');   // Abreviación o sigla
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gerencias');
    }
};
