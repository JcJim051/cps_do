<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('ejercicio_politico_origen_id');
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
