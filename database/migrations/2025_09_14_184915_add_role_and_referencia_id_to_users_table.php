<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->after('email')->nullable(); // almacena id del rol
            $table->unsignedBigInteger('referencia_id')->after('role_id')->nullable();
            $table->unsignedBigInteger('secretaria_id')->nullable();
            $table->unsignedBigInteger('gerencia_id')->nullable();
        
            $table->foreign('secretaria_id')
                  ->references('id')->on('secretarias')
                  ->onDelete('set null');

            $table->foreign('gerencia_id')
                  ->references('id')->on('gerencias')
                  ->onDelete('set null');

            // Si más adelante decides activar relación:
            // $table->foreign('referencia_id')
            //       ->references('id')->on('referencias')
            //       ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar las foreign keys primero
            if (Schema::hasColumn('users', 'secretaria_id')) {
                $table->dropForeign(['secretaria_id']);
                $table->dropColumn('secretaria_id');
            }

            if (Schema::hasColumn('users', 'gerencia_id')) {
                $table->dropForeign(['gerencia_id']);
                $table->dropColumn('gerencia_id');
            }

            if (Schema::hasColumn('users', 'referencia_id')) {
                // Si la FK fue activada, también hay que eliminarla
                $table->dropColumn('referencia_id');
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropColumn('role_id');
            }
        });
    }
};
