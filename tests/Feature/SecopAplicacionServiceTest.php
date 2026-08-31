<?php

namespace Tests\Feature;

use App\Models\SecopInstantanea;
use App\Models\SecopVinculo;
use App\Models\Seguimiento;
use App\Services\SecopAplicacionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecopAplicacionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('estados', function (Blueprint $t) { $t->id(); $t->string('nombre'); $t->timestamps(); });
        Schema::create('seguimientos', function (Blueprint $t) {
            $t->id(); $t->string('tipo')->default('contrato'); $t->unsignedBigInteger('estado_contrato_id')->nullable();
            $t->string('estado_secop')->nullable(); $t->integer('anio')->nullable(); $t->string('numero_contrato')->nullable();
            $t->date('fecha_acta_inicio')->nullable(); $t->date('fecha_finalizacion')->nullable(); $t->integer('tiempo_ejecucion_dias')->nullable();
            $t->decimal('valor_mensual', 18, 2)->nullable(); $t->decimal('valor_total', 18, 2)->nullable(); $t->string('adicion')->nullable(); $t->date('fecha_acta_inicio_adicion')->nullable();
            $t->date('fecha_finalizacion_adicion')->nullable(); $t->integer('tiempo_ejecucion_dias_adicion')->nullable();
            $t->integer('tiempo_extension_secop_dias')->nullable(); $t->integer('tiempo_suspension_dias')->nullable();
            $t->integer('tiempo_total_ejecucion_dias')->nullable(); $t->integer('tiempo_total_calendario_dias')->nullable(); $t->decimal('valor_adicion', 18, 2)->nullable();
            $t->decimal('valor_total_contrato', 18, 2)->nullable(); $t->boolean('aut_despacho')->default(false);
            $t->date('fecha_aut_despacho')->nullable(); $t->timestamp('ultima_actualizacion_secop')->nullable(); $t->timestamps();
        });
        Schema::create('secop_vinculos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seguimiento_id')->nullable(); $t->string('fuente_secop'); $t->string('identificador_externo');
            $t->json('campos_excluidos')->nullable(); $t->json('origenes_campos')->nullable(); $t->timestamp('ultima_aplicacion_at')->nullable();
            $t->text('ultimo_error')->nullable(); $t->json('ultimo_resultado')->nullable(); $t->timestamps();
        });
        Schema::create('secop_instantaneas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vinculo_id'); $t->string('estado')->nullable(); $t->string('numero_contrato')->nullable();
            $t->date('fecha_firma')->nullable(); $t->date('fecha_inicio')->nullable(); $t->date('fecha_fin')->nullable();
            $t->integer('duracion_inicial_dias')->nullable(); $t->integer('dias_adicionados')->nullable();
            $t->decimal('meses_adicionados', 12, 2)->nullable(); $t->string('unidad_duracion')->nullable();
            $t->boolean('marcacion_adicion')->nullable(); $t->decimal('valor_contrato',18,2)->nullable();
            $t->decimal('valor_adiciones',18,2)->nullable(); $t->decimal('valor_total',18,2)->nullable();
            $t->string('hash')->nullable(); $t->timestamp('consultado_at'); $t->timestamp('ultima_actualizacion_fuente')->nullable(); $t->timestamps();
        });
        Schema::create('secop_actualizaciones_seguimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seguimiento_id'); $t->unsignedBigInteger('vinculo_id')->nullable(); $t->unsignedBigInteger('instantanea_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->string('fuente_secop'); $t->string('identificador_externo'); $t->string('modo'); $t->string('resultado');
            $t->json('valores_anteriores')->nullable(); $t->json('valores_nuevos')->nullable(); $t->json('campos_aplicados')->nullable();
            $t->json('campos_omitidos')->nullable(); $t->json('origenes')->nullable(); $t->json('alertas')->nullable(); $t->string('hash_aplicacion')->nullable();
            $t->timestamp('aplicado_at'); $t->timestamps();
        });
        DB::table('estados')->insert([['nombre' => 'CONTRATADO'], ['nombre' => 'LIQUIDADO'], ['nombre' => 'SUSPENDIDO'], ['nombre' => 'APROBADO'], ['nombre' => 'CANCELADO']]);
    }

    public function test_piloto_59_2026_aplica_dias_valor_y_adicion_derivada_sin_autorizar(): void
    {
        $tracking = Seguimiento::create(['valor_mensual' => 7580000, 'valor_total' => 34110000, 'valor_total_contrato' => 34110000]);
        $link = SecopVinculo::create(['seguimiento_id' => $tracking->id, 'fuente_secop' => 'secop2', 'identificador_externo' => 'CO1.PCCNTR.8933158']);
        SecopInstantanea::create([
            'vinculo_id' => $link->id, 'estado' => 'Modificado', 'numero_contrato' => '59-2026',
            'fecha_firma' => '2026-01-15', 'fecha_inicio' => '2026-01-19', 'fecha_fin' => '2026-08-24',
            'duracion_inicial_dias' => 135, 'dias_adicionados' => 81,
            'valor_contrato' => 51038666.67, 'valor_total' => 51038666.67,
            'hash' => 'pilot', 'consultado_at' => now(), 'ultima_actualizacion_fuente' => '2026-06-19 00:00:00',
        ]);

        $result = app(SecopAplicacionService::class)->apply($link->fresh(['seguimiento', 'ultimaInstantanea']), 'prueba');
        $fresh = $tracking->fresh();
        $this->assertSame('actualizado', $result['resultado']);
        $this->assertSame('59-2026', $fresh->numero_contrato);
        $this->assertSame(135, $fresh->tiempo_ejecucion_dias);
        $this->assertSame(67, $fresh->tiempo_ejecucion_dias_adicion);
        $this->assertSame(14, $fresh->tiempo_suspension_dias);
        $this->assertSame(81, $fresh->tiempo_extension_secop_dias);
        $this->assertSame(202, $fresh->tiempo_total_ejecucion_dias);
        $this->assertSame(216, $fresh->tiempo_total_calendario_dias);
        $this->assertSame('2026-08-24', $fresh->fecha_finalizacion_adicion->format('Y-m-d'));
        $this->assertEqualsWithDelta(51038666.67, (float) $fresh->valor_total_contrato, .01);
        $this->assertEqualsWithDelta(16928666.67, (float) $fresh->valor_adicion, .01);
        $this->assertFalse((bool) $fresh->aut_despacho);
        $this->assertSame('Derivado', $link->fresh()->origenes_campos['valor_adicion']);
        $secondPreview = app(SecopAplicacionService::class)->preview($link->fresh(['seguimiento', 'ultimaInstantanea']));
        $this->assertSame([], $secondPreview['cambios'], json_encode($secondPreview['cambios']));
        app(SecopAplicacionService::class)->apply($link->fresh(['seguimiento', 'ultimaInstantanea']), 'prueba');
        $this->assertDatabaseCount('secop_actualizaciones_seguimientos', 1);
    }

    public function test_edicion_manual_excluye_campo_y_nulo_secop_no_borra(): void
    {
        $tracking = Seguimiento::create(['numero_contrato' => 'LOCAL', 'valor_total' => 100]);
        $link = SecopVinculo::create(['seguimiento_id' => $tracking->id, 'fuente_secop' => 'secop2', 'identificador_externo' => 'x']);
        SecopInstantanea::create(['vinculo_id' => $link->id, 'numero_contrato' => null, 'valor_total' => 200, 'hash' => 'x', 'consultado_at' => now()]);
        $tracking->valor_total = 150; $tracking->save();
        $this->assertContains('valor_total', $link->fresh()->campos_excluidos);
        app(SecopAplicacionService::class)->apply($link->fresh(['seguimiento', 'ultimaInstantanea']), 'prueba');
        $this->assertSame('LOCAL', $tracking->fresh()->numero_contrato);
        $this->assertEquals(150, (float) $tracking->fresh()->valor_total);
    }

    public function test_estado_secop_en_ejecucion_cambia_estado_operativo_a_contratado(): void
    {
        $approvedId = DB::table('estados')->where('nombre', 'APROBADO')->value('id');
        $contractedId = DB::table('estados')->where('nombre', 'CONTRATADO')->value('id');
        $tracking = Seguimiento::create(['estado_contrato_id' => $approvedId]);
        $link = SecopVinculo::create([
            'seguimiento_id' => $tracking->id,
            'fuente_secop' => 'secop2',
            'identificador_externo' => 'contrato-en-ejecucion',
        ]);
        SecopInstantanea::create([
            'vinculo_id' => $link->id,
            'estado' => 'En ejecución',
            'hash' => 'estado-en-ejecucion',
            'consultado_at' => now(),
        ]);

        app(SecopAplicacionService::class)->apply(
            $link->fresh(['seguimiento', 'ultimaInstantanea']),
            'prueba'
        );

        $fresh = $tracking->fresh();
        $this->assertSame((int) $contractedId, (int) $fresh->estado_contrato_id);
        $this->assertSame('En ejecución', $fresh->estado_secop);
        $this->assertSame('SECOP', $link->fresh()->origenes_campos['estado_contrato_id']);
    }
}
