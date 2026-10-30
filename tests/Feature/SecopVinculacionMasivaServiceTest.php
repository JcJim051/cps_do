<?php

namespace Tests\Feature;

use App\Jobs\ProcesarVinculacionSecopExacta;
use App\Models\Persona;
use App\Models\SecopConciliacionLote;
use App\Models\Seguimiento;
use App\Services\SecopAlcanceEntidadService;
use App\Services\SecopConciliacionService;
use App\Services\DatosAbiertosSecopService;
use App\Services\SecopVinculacionMasivaService;
use App\Services\SecopVinculacionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SecopVinculacionMasivaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Schema::dropAllTables();

        Schema::create('personas', function (Blueprint $table) {
            $table->id(); $table->string('nombre_contratista')->nullable(); $table->string('cedula_o_nit')->nullable(); $table->timestamps();
        });
        Schema::create('secretarias', function (Blueprint $table) {
            $table->id(); $table->string('nombre'); $table->string('nit_secop')->nullable();
            $table->boolean('auditoria_secop_incluida')->default(false); $table->boolean('es_descentralizada')->default(false); $table->timestamps();
        });
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('persona_id'); $table->string('tipo')->default('contrato');
            $table->unsignedBigInteger('secretaria_id')->nullable(); $table->unsignedBigInteger('estado_contrato_id')->nullable();
            $table->integer('anio')->nullable(); $table->string('numero_contrato')->nullable();
            $table->boolean('aut_despacho')->default(false); $table->boolean('aut_planeacion')->default(false); $table->timestamps();
        });
        Schema::create('secop_vinculos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seguimiento_id')->nullable(); $table->timestamps();
        });
        Schema::create('secop_conciliacion_lotes', function (Blueprint $table) {
            $table->id(); $table->integer('anio')->default(2026); $table->unsignedBigInteger('secretaria_id')->nullable();
            $table->unsignedBigInteger('estado_contrato_id')->nullable(); $table->json('secretaria_ids')->nullable(); $table->json('nit_entidades')->nullable();
            $table->unsignedBigInteger('iniciado_por')->nullable(); $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('cursor_persona_id')->default(0); $table->integer('total_personas')->default(0);
            $table->integer('personas_procesadas')->default(0); $table->integer('seguimientos_revisados')->default(0);
            $table->integer('coincidencias_exactas')->default(0); $table->integer('vinculos_creados')->default(0);
            $table->json('resultados')->nullable(); $table->integer('errores')->default(0); $table->text('ultimo_error')->nullable();
            $table->timestamp('iniciado_at')->nullable(); $table->timestamp('finalizado_at')->nullable(); $table->timestamps();
        });
        Schema::create('secop_conciliacion_revisiones', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('lote_id'); $table->unsignedBigInteger('seguimiento_id');
            $table->string('fuente_secop'); $table->string('identificador_externo'); $table->string('referencia_contrato')->nullable();
            $table->integer('puntaje')->nullable(); $table->integer('margen')->nullable(); $table->json('razones')->nullable();
            $table->json('bloqueos')->nullable(); $table->json('candidato'); $table->string('estado')->default('pendiente');
            $table->unsignedBigInteger('resuelto_por')->nullable(); $table->timestamp('resuelto_at')->nullable();
            $table->text('ultimo_error')->nullable(); $table->timestamps();
            $table->unique(['lote_id', 'seguimiento_id']);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lote_solo_incluye_personas_con_seguimientos_departamentales_sin_vinculo(): void
    {
        Queue::fake();
        $this->secretaria(1, 'DEPARTAMENTO DEL META', '8920001488', true);
        $this->secretaria(2, 'ENTIDAD EXTERNA', '9000000001', false);

        $included = Persona::create(['nombre_contratista' => 'Incluida', 'cedula_o_nit' => '1']);
        $external = Persona::create(['nombre_contratista' => 'Externa', 'cedula_o_nit' => '2']);
        $linked = Persona::create(['nombre_contratista' => 'Vinculada', 'cedula_o_nit' => '3']);
        $this->tracking($included, 1);
        $this->tracking($external, 2);
        $linkedTracking = $this->tracking($linked, 1);
        DB::table('secop_vinculos')->insert(['seguimiento_id' => $linkedTracking->id, 'created_at' => now(), 'updated_at' => now()]);

        $service = $this->service();
        $started = $service->start([], 10);

        $this->assertSame(1, $started['lote']->total_personas);
        $this->assertSame([1], $started['lote']->secretaria_ids);
        $this->assertSame(['8920001488'], $started['lote']->nit_entidades);
        Queue::assertPushed(ProcesarVinculacionSecopExacta::class);
    }

    public function test_no_inicia_si_una_entidad_incluida_no_tiene_nit(): void
    {
        Queue::fake();
        $this->secretaria(1, 'DESCENTRALIZADA SIN NIT', null, true);
        $person = Persona::create(['nombre_contratista' => 'Persona', 'cedula_o_nit' => '1']);
        $this->tracking($person, 1);

        $this->expectException(ValidationException::class);
        try {
            $this->service()->start([], 10);
        } finally {
            Queue::assertNothingPushed();
        }
    }

    public function test_incluye_seguimiento_autorizado_aunque_no_tenga_numero_de_contrato(): void
    {
        Queue::fake();
        $this->secretaria(1, 'DEPARTAMENTO DEL META', '8920001488', true);
        $person = Persona::create(['nombre_contratista' => 'Sin número', 'cedula_o_nit' => '123']);
        $tracking = new Seguimiento([
            'persona_id' => $person->id,
            'tipo' => 'contrato',
            'secretaria_id' => 1,
            'anio' => 2026,
            'numero_contrato' => null,
            'aut_despacho' => true,
            'aut_planeacion' => true,
        ]);
        $tracking->skipAutoCalculation()->skipSecopExclusions()->save();

        $started = $this->service()->start([], 10);

        $this->assertSame(1, $started['lote']->total_personas);
        Queue::assertPushed(ProcesarVinculacionSecopExacta::class);
    }

    public function test_lote_acumula_los_motivos_de_no_vinculacion(): void
    {
        $this->secretaria(1, 'DEPARTAMENTO DEL META', '8920001488', true);
        $person = Persona::create(['nombre_contratista' => 'Persona', 'cedula_o_nit' => '1']);
        $first = $this->tracking($person, 1, '100-2026');
        $second = $this->tracking($person, 1, '101-2026');
        $lote = SecopConciliacionLote::create([
            'anio' => 2026, 'estado' => 'pendiente', 'total_personas' => 1,
            'secretaria_ids' => [1], 'nit_entidades' => ['8920001488'],
        ]);

        $conciliation = Mockery::mock(SecopConciliacionService::class);
        $conciliation->shouldReceive('conciliarPersona')->once()
            ->withArgs(fn ($target, $desde, $anio, $other, $vigencia, $nits, $ids) =>
                $target->is($person) && $anio === 2026 && $other === false && $vigencia === 2026
                && $nits === ['8920001488'] && $ids === [1]
            )
            ->andReturn([
                'consulta_secop_disponible' => true,
                'filas' => collect([
                    ['seguimiento' => $first, 'estado' => 'sin_resultado', 'seguro' => false],
                    [
                        'seguimiento' => $second,
                        'estado' => 'requiere_revision',
                        'seguro' => false,
                        'puntaje' => 62,
                        'margen' => 62,
                        'razones' => ['Cédula exacta', 'Valor aproximado'],
                        'bloqueos' => ['valor_fuera_de_rango'],
                        'candidato' => [
                            'fuente_codigo' => 'secop2',
                            'identificador_externo' => 'CO1.PCCNTR.REVIEW',
                            'referencia_contrato' => '2458 DE 2026',
                        ],
                    ],
                ]),
                'contratos_sin_seguimiento' => collect(),
            ]);
        $links = Mockery::mock(SecopVinculacionService::class);
        $links->shouldNotReceive('vincularSeguimientoDesdeCandidato');
        $secop = Mockery::mock(DatosAbiertosSecopService::class);
        $secop->shouldReceive('consultarPorDocumentosVigencia')->once()
            ->with(['1'], 2026, 50000, ['8920001488'])
            ->andReturn(['1' => []]);
        $service = new SecopVinculacionMasivaService(
            $conciliation,
            $links,
            new SecopAlcanceEntidadService(),
            $secop,
        );

        $this->assertFalse($service->processNext($lote->id));
        $lote->refresh();
        $this->assertSame('finalizado', $lote->estado);
        $this->assertSame(2, $lote->seguimientos_revisados);
        $this->assertSame(1, $lote->resultados['sin_contrato_encontrado']);
        $this->assertSame(1, $lote->resultados['entidad_o_documento_diferente']);
        $this->assertSame(1, $lote->resultados['motivos_revision']['valor_fuera_de_rango']);
        $this->assertDatabaseHas('secop_conciliacion_revisiones', [
            'lote_id' => $lote->id,
            'seguimiento_id' => $second->id,
            'identificador_externo' => 'CO1.PCCNTR.REVIEW',
            'estado' => 'pendiente',
        ]);
    }

    private function service(): SecopVinculacionMasivaService
    {
        return new SecopVinculacionMasivaService(
            Mockery::mock(SecopConciliacionService::class),
            Mockery::mock(SecopVinculacionService::class),
            new SecopAlcanceEntidadService(),
            Mockery::mock(DatosAbiertosSecopService::class),
        );
    }

    private function secretaria(int $id, string $name, ?string $nit, bool $included): void
    {
        DB::table('secretarias')->insert([
            'id' => $id, 'nombre' => $name, 'nit_secop' => $nit, 'auditoria_secop_incluida' => $included,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function tracking(Persona $person, int $secretariaId, string $number = '59-2026'): Seguimiento
    {
        $tracking = new Seguimiento([
            'persona_id' => $person->id, 'tipo' => 'contrato', 'secretaria_id' => $secretariaId,
            'anio' => 2026, 'numero_contrato' => $number,
        ]);
        $tracking->skipAutoCalculation()->skipSecopExclusions()->save();
        return $tracking;
    }
}
