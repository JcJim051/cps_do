<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientos', 'estado_aprobacion_adicion')) {
                $table->string('estado_aprobacion_adicion', 20)->nullable()->after('estado_aprobacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            if (Schema::hasColumn('seguimientos', 'estado_aprobacion_adicion')) {
                $table->dropColumn('estado_aprobacion_adicion');
            }
        });
    }
};

