<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\SecopAuditoriaLote;
use App\Jobs\ProcesarAuditoriaSecop;
use App\Services\SecopAuditoriaService;
use App\Services\SecopConciliacionService;
use App\Services\SecopVigenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SecopAuditoriaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::clearResolvedInstance('db.schema');
        Schema::dropAllTables();
        Schema::create('personas', function (Blueprint $t) { $t->id(); $t->string('nombre_contratista')->nullable(); $t->string('cedula_o_nit')->nullable(); $t->timestamps(); });
        Schema::create('secretarias', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->string('nit_secop')->nullable(); $t->boolean('auditoria_secop_incluida')->default(false); $t->timestamps();
        });
        DB::table('secretarias')->insert([
            'id' => 1, 'nombre' => 'DEPARTAMENTO DEL META', 'nit_secop' => '8920001488',
            'auditoria_secop_incluida' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('secop_auditoria_lotes', function (Blueprint $t) {
            $t->id(); $t->integer('anio')->default(2026); $t->string('criterio_vigencia'); $t->string('alcance_entidad')->default('sin_filtro_entidad'); $t->string('filtro_persona')->nullable(); $t->json('persona_ids')->nullable(); $t->json('secretaria_ids')->nullable(); $t->json('nit_entidades')->nullable(); $t->unsignedBigInteger('iniciado_por')->nullable();
            $t->string('estado'); $t->unsignedBigInteger('cursor_persona_id')->default(0); $t->integer('total_personas')->default(0);
            $t->integer('personas_procesadas')->default(0); $t->integer('solo_integra')->default(0); $t->integer('solo_secop')->default(0);
            $t->integer('requiere_revision')->default(0); $t->integer('coincidencias_exactas')->default(0); $t->integer('alertas_secop')->default(0);
            $t->integer('errores')->default(0); $t->text('ultimo_error')->nullable(); $t->timestamp('iniciado_at')->nullable(); $t->timestamp('finalizado_at')->nullable(); $t->timestamps();
        });
        Schema::create('secop_auditoria_hallazgos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lote_id'); $t->unsignedBigInteger('persona_id'); $t->unsignedBigInteger('seguimiento_id')->nullable();
            $t->string('tipo'); $t->string('fuente_secop')->nullable(); $t->string('identificador_externo')->nullable(); $t->string('referencia_contrato')->nullable();
            $t->string('nombre_entidad')->nullable(); $t->string('estado_secop')->nullable(); $t->date('fecha_firma')->nullable(); $t->date('fecha_inicio')->nullable();
            $t->date('fecha_fin')->nullable(); $t->decimal('valor_total', 18, 2)->nullable(); $t->json('detalle')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_nuevo_lote_clasifica_contratos_y_conserva_cursor_reanudable(): void
    {
        Persona::create(['nombre_contratista' => 'Uno', 'cedula_o_nit' => '1']);
        Persona::create(['nombre_contratista' => 'Dos', 'cedula_o_nit' => '2']);
        $lote = SecopAuditoriaLote::create(['anio' => 2026, 'criterio_vigencia' => 'ejecucion_superpuesta', 'estado' => 'pendiente', 'total_personas' => 2]);
        $conciliacion = Mockery::mock(SecopConciliacionService::class);
        $conciliacion->shouldReceive('conciliarPersona')->twice()->withArgs(fn ($persona, $desde, $anio, $other, $vigencia) =>
            $desde === '2026-01-01' && $anio === null && $other === false && $vigencia === 2026
        )->andReturnUsing(fn () => [
            'consulta_secop_disponible' => true,
            'filas' => collect(),
            'contratos_sin_seguimiento' => collect([
                $this->contract('En ejecución', 'ACTIVE'),
                $this->contract('Cancelado', 'CANCELLED'),
            ]),
        ]);
        $service = new SecopAuditoriaService($conciliacion, new SecopVigenciaService());

        $this->assertTrue($service->processNext($lote->id));
        $afterFirst = $lote->fresh();
        $this->assertSame(1, $afterFirst->personas_procesadas);
        $this->assertSame(1, $afterFirst->solo_secop);
        $this->assertSame(1, $afterFirst->alertas_secop);

        $this->assertFalse($service->processNext($lote->id));
        $finished = $lote->fresh();
        $this->assertSame('finalizado', $finished->estado);
        $this->assertSame(2, $finished->personas_procesadas);
        $this->assertSame(2, $finished->solo_secop);
        $this->assertSame(2, $finished->alertas_secop);
        $this->assertDatabaseCount('secop_auditoria_hallazgos', 4);
    }

    public function test_auditoria_por_cedula_crea_y_procesa_un_lote_solo_para_esa_persona(): void
    {
        Queue::fake();
        $target = Persona::create(['nombre_contratista' => 'Persona objetivo', 'cedula_o_nit' => '1030590916']);
        Persona::create(['nombre_contratista' => 'Otra persona', 'cedula_o_nit' => '999999']);

        $conciliacion = Mockery::mock(SecopConciliacionService::class);
        $conciliacion->shouldReceive('conciliarPersona')->once()
            ->withArgs(fn ($persona) => $persona->is($target))
            ->andReturn([
                'consulta_secop_disponible' => true,
                'filas' => collect(),
                'contratos_sin_seguimiento' => collect(),
            ]);
        $service = new SecopAuditoriaService($conciliacion, new SecopVigenciaService());

        $started = $service->start(1, '1030590916');
        $lote = $started['lote'];

        $this->assertTrue($started['creado']);
        $this->assertSame(1, $lote->total_personas);
        $this->assertSame('1030590916', $lote->filtro_persona);
        $this->assertSame([$target->id], $lote->persona_ids);
        $this->assertSame('departamental_parametrizado', $lote->alcance_entidad);
        $this->assertSame([1], $lote->secretaria_ids);
        $this->assertSame(['8920001488'], $lote->nit_entidades);
        Queue::assertPushed(ProcesarAuditoriaSecop::class, fn ($job) => $job->loteId === $lote->id);

        $this->assertFalse($service->processNext($lote->id));
        $this->assertSame('finalizado', $lote->fresh()->estado);
        $this->assertSame(1, $lote->fresh()->personas_procesadas);
    }

    public function test_no_inicia_si_una_entidad_departamental_incluida_no_tiene_nit(): void
    {
        Queue::fake();
        DB::table('secretarias')->insert([
            'nombre' => 'DESCENTRALIZADA SIN NIT', 'nit_secop' => null,
            'auditoria_secop_incluida' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Persona::create(['nombre_contratista' => 'Persona', 'cedula_o_nit' => '123']);
        $service = new SecopAuditoriaService(
            Mockery::mock(SecopConciliacionService::class),
            new SecopVigenciaService(),
        );

        try {
            $service->start(1, '123');
            $this->fail('La auditoría no debía iniciar con entidades departamentales sin NIT.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Falta parametrizar el NIT SECOP', $exception->getMessage());
        }
        Queue::assertNothingPushed();
    }

    public function test_un_error_de_consulta_se_registra_y_no_deja_el_lote_bloqueado(): void
    {
        Persona::create(['nombre_contratista' => 'Error', 'cedula_o_nit' => '3']);
        $lote = SecopAuditoriaLote::create(['anio' => 2026, 'criterio_vigencia' => 'ejecucion_superpuesta', 'estado' => 'pendiente', 'total_personas' => 1]);
        $conciliacion = Mockery::mock(SecopConciliacionService::class);
        $conciliacion->shouldReceive('conciliarPersona')->once()->andThrow(new \RuntimeException('Sin respuesta'));

        $result = (new SecopAuditoriaService($conciliacion, new SecopVigenciaService()))->processNext($lote->id);

        $this->assertFalse($result);
        $this->assertSame('finalizado', $lote->fresh()->estado);
        $this->assertSame(1, $lote->fresh()->errores);
        $this->assertStringContainsString('Sin respuesta', $lote->fresh()->ultimo_error);
    }

    public function test_error_en_auditoria_puntual_no_consume_la_persona_y_queda_inconclusa(): void
    {
        $persona = Persona::create(['nombre_contratista' => 'Pendiente SECOP', 'cedula_o_nit' => '86075713']);
        $lote = SecopAuditoriaLote::create([
            'anio' => 2026,
            'criterio_vigencia' => 'ejecucion_superpuesta',
            'filtro_persona' => '86075713',
            'persona_ids' => [$persona->id],
            'estado' => 'pendiente',
            'total_personas' => 1,
        ]);
        $conciliacion = Mockery::mock(SecopConciliacionService::class);
        $conciliacion->shouldReceive('conciliarPersona')->once()->andReturn([
            'consulta_secop_disponible' => false,
            'filas' => collect(),
            'contratos_sin_seguimiento' => collect(),
        ]);
        $service = new SecopAuditoriaService($conciliacion, new SecopVigenciaService());

        try {
            $service->processNext($lote->id);
            $this->fail('La consulta puntual debía conservar la persona para reintentar.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('SECOP no respondió', $exception->getMessage());
        }

        $this->assertSame(0, $lote->fresh()->personas_procesadas);
        $this->assertSame('procesando', $lote->fresh()->estado);

        (new ProcesarAuditoriaSecop($lote->id))->failed(new \RuntimeException('timeout'));
        $this->assertSame('fallido', $lote->fresh()->estado);
        $this->assertSame(1, $lote->fresh()->errores);
        $this->assertStringContainsString('puede volver a auditarse', $lote->fresh()->ultimo_error);
    }

    public function test_lote_historico_conserva_su_metodologia_original(): void
    {
        Persona::create(['nombre_contratista' => 'Histórico', 'cedula_o_nit' => '4']);
        $lote = SecopAuditoriaLote::create(['anio' => 2026, 'criterio_vigencia' => 'firma_desde_anio', 'estado' => 'pendiente', 'total_personas' => 1]);
        $conciliacion = Mockery::mock(SecopConciliacionService::class);
        $conciliacion->shouldReceive('conciliarPersona')->once()->with(Mockery::type(Persona::class), '2026-01-01', 2026, false)->andReturn([
            'consulta_secop_disponible' => true, 'filas' => collect(),
            'contratos_sin_seguimiento' => collect([$this->contract('Cancelado', 'OLD')]),
        ]);

        (new SecopAuditoriaService($conciliacion, new SecopVigenciaService()))->processNext($lote->id);

        $this->assertSame(1, $lote->fresh()->solo_secop);
        $this->assertSame(0, $lote->fresh()->alertas_secop);
        $this->assertDatabaseHas('secop_auditoria_hallazgos', ['lote_id' => $lote->id, 'tipo' => 'solo_secop']);
    }

    private function contract(string $state, string $id): array
    {
        return [
            'fuente_codigo' => 'secop2', 'identificador_externo' => $id, 'referencia_contrato' => $id,
            'nombre_entidad' => 'Entidad', 'estado' => $state, 'fecha_firma' => '2026-01-01',
            'fecha_inicio' => '2026-01-02', 'fecha_fin' => '2026-12-01', 'valor_total_con_adiciones' => 100,
        ];
    }
}
