<?php

namespace Tests\Unit;

use App\Exports\SeguimientoExport;
use App\Models\Seguimiento;
use App\Services\SeguimientoFilterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeguimientoFilterServiceTest extends TestCase
{
    private SeguimientoFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_contratista');
            $table->string('cedula_o_nit')->nullable();
            $table->timestamps();
        });
        Schema::create('referencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
        Schema::create('persona_referencia', function (Blueprint $table) {
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('referencia_id');
        });
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('estado_contrato_id')->nullable();
            $table->unsignedBigInteger('secretaria_id')->nullable();
            $table->unsignedBigInteger('gerencia_id')->nullable();
            $table->integer('anio')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('observaciones_contrato')->nullable();
            $table->timestamps();
        });

        DB::table('personas')->insert([
            ['id' => 1, 'nombre_contratista' => 'Persona Uno', 'cedula_o_nit' => '101', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre_contratista' => 'Persona Dos', 'cedula_o_nit' => '202', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre_contratista' => 'Persona Tres', 'cedula_o_nit' => '303', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('referencias')->insert([
            ['id' => 7, 'nombre' => 'Referencia A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'nombre' => 'Referencia B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('persona_referencia')->insert([
            ['persona_id' => 1, 'referencia_id' => 7],
            ['persona_id' => 2, 'referencia_id' => 8],
            ['persona_id' => 3, 'referencia_id' => 8],
        ]);
        DB::table('seguimientos')->insert([
            ['id' => 11, 'persona_id' => 1, 'estado_contrato_id' => 1, 'secretaria_id' => 10, 'gerencia_id' => 100, 'anio' => 2026, 'observaciones_contrato' => 'prioritario', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'persona_id' => 2, 'estado_contrato_id' => 2, 'secretaria_id' => 20, 'gerencia_id' => 200, 'anio' => 2026, 'observaciones_contrato' => 'normal', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'persona_id' => 3, 'estado_contrato_id' => 1, 'secretaria_id' => 10, 'gerencia_id' => 200, 'anio' => 2025, 'observaciones' => 'prioritario', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->service = app(SeguimientoFilterService::class);
    }

    public function test_sanitiza_json_valores_individuales_repetidos_y_malformados(): void
    {
        $this->assertSame([10, 20], $this->service->ids('["10","20","10","x",null]'));
        $this->assertSame([10], $this->service->ids('10'));
        $this->assertSame([], $this->service->ids('texto-invalido'));
    }

    public function test_aplica_or_dentro_de_un_filtro_y_and_entre_filtros(): void
    {
        $query = Seguimiento::query();
        $this->service->apply($query, [
            'secretaria_id' => '["10","20"]',
            'estado_contrato_id' => '["1"]',
        ]);

        $this->assertSame([11, 13], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_referencias_coinciden_con_cualquiera_de_las_seleccionadas(): void
    {
        $query = Seguimiento::query();
        $this->service->apply($query, ['filtro_referencia' => '["7","8"]']);

        $this->assertSame([11, 12, 13], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_personas_multiples_y_alias_individual_antiguo_son_compatibles(): void
    {
        $multiple = Seguimiento::query();
        $this->service->apply($multiple, ['personas' => '["1","2"]']);
        $this->assertSame([11, 12], $multiple->orderBy('id')->pluck('id')->all());

        $legacy = Seguimiento::query();
        $this->service->apply($legacy, ['persona_cedula' => '2']);
        $this->assertSame([12], $legacy->pluck('id')->all());
    }

    public function test_normaliza_enlaces_antiguos_para_restaurar_la_seleccion_visual(): void
    {
        $request = Request::create('/admin/seguimiento', 'GET', [
            'secretaria_id' => '10',
            'persona_id' => '1',
            'persona_cedula' => '2',
        ]);

        $people = $this->service->normalizeListRequest($request);

        $this->assertSame([1, 2], $people);
        $this->assertSame('[10]', $request->query('secretaria_id'));
        $this->assertSame('[1,2]', $request->query('personas'));
    }

    public function test_observaciones_se_combinan_con_los_selectores(): void
    {
        $query = Seguimiento::query();
        $this->service->apply($query, [
            'anio' => '["2026"]',
            'observaciones' => 'prioritario',
        ]);

        $this->assertSame([11], $query->pluck('id')->all());
    }

    public function test_exportacion_recibe_exactamente_los_registros_filtrados(): void
    {
        $query = Seguimiento::query()->with('persona');
        $this->service->apply($query, [
            'personas' => '["1","2"]',
            'estado_contrato_id' => '["1"]',
        ]);

        $export = new SeguimientoExport($query->get());

        $this->assertSame([11], $export->collection()->pluck('id')->all());
    }
}
