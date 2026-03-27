<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('seguimientos', 'ejercicio_politico_id')) {
            return;
        }

        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropForeign(['ejercicio_politico_id']);
            $table->dropIndex(['ejercicio_politico_id']);
            $table->dropColumn('ejercicio_politico_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('seguimientos', 'ejercicio_politico_id')) {
            return;
        }

        Schema::table('seguimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('ejercicio_politico_id')->nullable()->after('persona_id');
            $table->index('ejercicio_politico_id');
            $table->foreign('ejercicio_politico_id')
                ->references('id')
                ->on('ejercicios_politicos')
                ->onDelete('set null');
        });
    }
};
