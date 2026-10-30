<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecopSecretariaPciSeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Schema::dropAllTables();

        Schema::create('secretarias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('convencion');
            $table->string('nit_secop')->nullable();
            $table->string('nombre_secop')->nullable();
            $table->string('codigo_pci_secop')->nullable();
            $table->string('nombre_unidad_secop')->nullable();
            $table->timestamps();
        });
    }

    public function test_completa_pci_unidades_y_corrige_el_nit_duplicado_sin_sobrescribir_datos_manuales(): void
    {
        DB::table('secretarias')->insert([
            [
                'nombre' => 'JUR', 'convencion' => 'JUR', 'nit_secop' => '8920001488',
                'codigo_pci_secop' => null, 'nombre_unidad_secop' => null,
            ],
            [
                'nombre' => 'HOSPITAL VILLAVICENCIO', 'convencion' => 'HOSP VILLAO', 'nit_secop' => '800037021',
                'codigo_pci_secop' => null, 'nombre_unidad_secop' => null,
            ],
            [
                'nombre' => 'AIM', 'convencion' => 'AIM', 'nit_secop' => '9002205475',
                'codigo_pci_secop' => 'MANUAL', 'nombre_unidad_secop' => 'UNIDAD MANUAL',
            ],
        ]);

        $migration = require database_path('migrations/2026_10_30_000001_seed_secop_pci_and_units.php');
        $migration->up();

        $juridica = DB::table('secretarias')->where('convencion', 'JUR')->first();
        $this->assertSame('700817075', $juridica->codigo_pci_secop);
        $this->assertSame('JURIDICA', $juridica->nombre_unidad_secop);

        $hospital = DB::table('secretarias')->where('convencion', 'HOSP VILLAO')->first();
        $this->assertSame('892000501', $hospital->nit_secop);
        $this->assertSame('702938226', $hospital->codigo_pci_secop);
        $this->assertSame('HOSPITAL DEPARTAMENTAL DE VILLAVICENCIO ESE', $hospital->nombre_unidad_secop);

        $aim = DB::table('secretarias')->where('convencion', 'AIM')->first();
        $this->assertSame('MANUAL', $aim->codigo_pci_secop);
        $this->assertSame('UNIDAD MANUAL', $aim->nombre_unidad_secop);
    }
}
