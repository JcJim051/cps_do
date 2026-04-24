<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('seguimientos', 'aut_despacho_adicion')) {
                $table->boolean('aut_despacho_adicion')->default(false)->after('aut_administrativa');
            }
            if (!Schema::hasColumn('seguimientos', 'aut_planeacion_adicion')) {
                $table->boolean('aut_planeacion_adicion')->default(false)->after('aut_despacho_adicion');
            }
            if (!Schema::hasColumn('seguimientos', 'aut_administrativa_adicion')) {
                $table->boolean('aut_administrativa_adicion')->default(false)->after('aut_planeacion_adicion');
            }
            if (!Schema::hasColumn('seguimientos', 'fecha_aut_despacho_adicion')) {
                $table->date('fecha_aut_despacho_adicion')->nullable()->after('fecha_aut_administrativa');
            }
            if (!Schema::hasColumn('seguimientos', 'fecha_aut_planeacion_adicion')) {
                $table->date('fecha_aut_planeacion_adicion')->nullable()->after('fecha_aut_despacho_adicion');
            }
            if (!Schema::hasColumn('seguimientos', 'fecha_aut_administrativa_adicion')) {
                $table->date('fecha_aut_administrativa_adicion')->nullable()->after('fecha_aut_planeacion_adicion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $toDrop = [];
            foreach ([
                'aut_despacho_adicion',
                'aut_planeacion_adicion',
                'aut_administrativa_adicion',
                'fecha_aut_despacho_adicion',
                'fecha_aut_planeacion_adicion',
                'fecha_aut_administrativa_adicion',
            ] as $column) {
                if (Schema::hasColumn('seguimientos', $column)) {
                    $toDrop[] = $column;
                }
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

