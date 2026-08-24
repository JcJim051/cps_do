<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('prevalidaciones_contractuales', 'drive_protected_range_id')) {
            Schema::table('prevalidaciones_contractuales', fn (Blueprint $table) =>
                $table->unsignedBigInteger('drive_protected_range_id')->nullable()->after('seguimiento_id'));
        }
        if (!Schema::hasColumn('prevalidaciones_contractuales', 'drive_protegido_at')) {
            Schema::table('prevalidaciones_contractuales', fn (Blueprint $table) =>
                $table->timestamp('drive_protegido_at')->nullable()->after('drive_protected_range_id'));
        }
        if (!Schema::hasColumn('prevalidaciones_contractuales', 'drive_sincronizacion_pendiente')) {
            Schema::table('prevalidaciones_contractuales', fn (Blueprint $table) =>
                $table->boolean('drive_sincronizacion_pendiente')->default(false)->after('drive_protegido_at'));
        }
        if (!Schema::hasColumn('prevalidaciones_contractuales', 'drive_ultimo_error')) {
            Schema::table('prevalidaciones_contractuales', fn (Blueprint $table) =>
                $table->text('drive_ultimo_error')->nullable()->after('drive_sincronizacion_pendiente'));
        }
    }

    public function down(): void
    {
        Schema::table('prevalidaciones_contractuales', function (Blueprint $table) {
            $table->dropColumn([
                'drive_protected_range_id', 'drive_protegido_at',
                'drive_sincronizacion_pendiente', 'drive_ultimo_error',
            ]);
        });
    }
};
