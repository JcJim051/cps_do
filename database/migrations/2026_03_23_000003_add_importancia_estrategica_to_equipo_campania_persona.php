<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->boolean('importancia_estrategica')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('equipo_campania_persona', function (Blueprint $table) {
            $table->dropColumn('importancia_estrategica');
        });
    }
};
