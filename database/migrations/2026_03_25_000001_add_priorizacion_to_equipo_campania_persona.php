<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->unsignedTinyInteger('priorizacion')->nullable()->after('importancia_estrategica');
        });

        DB::statement("UPDATE equipo_campania_persona SET priorizacion = CASE prioridad
            WHEN 'Alta' THEN 1
            WHEN 'Importancia estrategica' THEN 1
            WHEN 'Media' THEN 2
            WHEN 'Baja' THEN 3
            ELSE priorizacion
        END");
    }

    public function down(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->dropColumn('priorizacion');
        });
    }
};
