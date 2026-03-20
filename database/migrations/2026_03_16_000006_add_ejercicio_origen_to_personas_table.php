<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->unsignedBigInteger('ejercicio_politico_origen_id')->nullable()->after('gerencia_id');
            $table->foreign('ejercicio_politico_origen_id')
                ->references('id')
                ->on('ejercicios_politicos')
                ->onDelete('set null');
            $table->index('ejercicio_politico_origen_id');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['ejercicio_politico_origen_id']);
            $table->dropIndex(['ejercicio_politico_origen_id']);
            $table->dropColumn('ejercicio_politico_origen_id');
        });
    }
};
