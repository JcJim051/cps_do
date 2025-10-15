<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('referencia_2')->nullable()->after('referencia_id'); // Ajusta 'referencia' si quieres que quede en otra posición
        });
    }
    
    public function down()
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn('referencia_2');
        });
    }
    
};
