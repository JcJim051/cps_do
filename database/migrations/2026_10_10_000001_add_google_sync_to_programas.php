<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropUnique('programas_cedula_unique');
        });

        Schema::table('programas', function (Blueprint $table) {
            $table->string('cedula', 50)->nullable()->change();
            $table->string('external_id', 80)->nullable()->unique()->after('id');
            $table->string('perfil')->nullable()->after('operador');
            $table->string('estado', 80)->nullable()->after('ref_2');
            $table->unsignedInteger('fila_origen')->nullable()->after('estado');
            $table->string('origen', 30)->default('manual')->after('fila_origen');
            $table->boolean('presente_en_origen')->default(true)->after('origen');
            $table->string('hash_origen', 64)->nullable()->after('presente_en_origen');
            $table->timestamp('ultima_sincronizacion_at')->nullable()->after('hash_origen');

            $table->index(['presente_en_origen', 'estado'], 'programas_presente_estado_index');
            $table->index(['municipio', 'operador'], 'programas_municipio_operador_index');
            $table->index('cedula', 'programas_cedula_index');
        });

        Schema::create('programa_fuentes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('spreadsheet_id');
            $table->string('spreadsheet_url');
            $table->string('hoja')->default('CONSOLIDADO GENERAL');
            $table->unsignedInteger('fila_encabezados')->default(1);
            $table->boolean('activa')->default(true);
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->json('ultimo_resumen')->nullable();
            $table->longText('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['spreadsheet_id', 'hoja']);
        });

        DB::table('programa_fuentes')->insert([
            'nombre' => 'Base de datos general de Programas',
            'spreadsheet_id' => '1LwzArY5Y16MyOotq4NBB8x_jyIe7sfbcIAc_YAprZ6k',
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1LwzArY5Y16MyOotq4NBB8x_jyIe7sfbcIAc_YAprZ6k',
            'hoja' => 'CONSOLIDADO GENERAL',
            'fila_encabezados' => 1,
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_fuentes');

        Schema::table('programas', function (Blueprint $table) {
            $table->dropIndex('programas_presente_estado_index');
            $table->dropIndex('programas_municipio_operador_index');
            $table->dropIndex('programas_cedula_index');
            $table->dropUnique('programas_external_id_unique');
            $table->dropColumn([
                'external_id', 'perfil', 'estado', 'fila_origen', 'origen',
                'presente_en_origen', 'hash_origen', 'ultima_sincronizacion_at',
            ]);
        });
    }
};
