<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('equipo_campania_persona_historial')) {
            Schema::create('equipo_campania_persona_historial', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('equipo_campania_id');
                $table->unsignedBigInteger('persona_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('accion', 20); // agregado | retirado
                $table->timestamps();

                $table->index(['persona_id', 'equipo_campania_id'], 'ecp_historial_persona_equipo_idx');
            });
        } else {
            Schema::table('equipo_campania_persona_historial', function (Blueprint $table) {
                $table->index(['persona_id', 'equipo_campania_id'], 'ecp_historial_persona_equipo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_campania_persona_historial');
    }
};
