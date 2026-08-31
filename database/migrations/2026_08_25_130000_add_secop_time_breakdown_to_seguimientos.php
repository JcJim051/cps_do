<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->unsignedInteger('tiempo_extension_secop_dias')->nullable()->after('tiempo_ejecucion_dias_adicion');
            $table->unsignedInteger('tiempo_suspension_dias')->nullable()->after('tiempo_extension_secop_dias');
            $table->unsignedInteger('tiempo_total_calendario_dias')->nullable()->after('tiempo_total_ejecucion_dias');
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropColumn(['tiempo_extension_secop_dias', 'tiempo_suspension_dias', 'tiempo_total_calendario_dias']);
        });
    }
};
