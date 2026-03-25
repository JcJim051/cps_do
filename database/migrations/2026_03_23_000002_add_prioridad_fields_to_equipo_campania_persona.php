<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->unsignedTinyInteger('dedicacion')->nullable();
            $table->unsignedTinyInteger('cumplimiento_actividades')->nullable();
            $table->unsignedTinyInteger('cumplimiento_metas')->nullable();
            $table->string('prioridad', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->dropColumn([
                'dedicacion',
                'cumplimiento_actividades',
                'cumplimiento_metas',
                'prioridad',
            ]);
        });
    }
};
