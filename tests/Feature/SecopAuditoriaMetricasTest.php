<?php

namespace Tests\Feature;

use App\Models\SecopAuditoriaHallazgo;
use App\Models\SecopAuditoriaLote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecopAuditoriaMetricasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('secop_auditoria_lotes', function (Blueprint $table) {
            $table->id(); $table->integer('anio'); $table->string('criterio_vigencia'); $table->string('alcance_entidad')->default('sin_filtro_entidad');
            $table->string('filtro_persona')->nullable(); $table->json('persona_ids')->nullable(); $table->json('secretaria_ids')->nullable(); $table->json('nit_entidades')->nullable();
            $table->unsignedBigInteger('iniciado_por')->nullable();
            $table->string('estado')->default('finalizado'); $table->unsignedBigInteger('cursor_persona_id')->default(0); $table->integer('total_personas')->default(0);
            $table->integer('personas_procesadas')->default(0); $table->integer('solo_integra')->default(0); $table->integer('solo_secop')->default(0);
            $table->integer('requiere_revision')->default(0); $table->integer('coincidencias_exactas')->default(0); $table->integer('alertas_secop')->default(0);
            $table->integer('errores')->default(0); $table->text('ultimo_error')->nullable(); $table->timestamp('iniciado_at')->nullable(); $table->timestamp('finalizado_at')->nullable(); $table->timestamps();
        });
        Schema::create('secop_auditoria_hallazgos', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('lote_id'); $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('seguimiento_id')->nullable(); $table->string('tipo'); $table->string('fuente_secop')->nullable();
            $table->string('identificador_externo')->nullable(); $table->string('referencia_contrato')->nullable(); $table->string('nombre_entidad')->nullable();
            $table->string('estado_secop')->nullable(); $table->date('fecha_firma')->nullable(); $table->date('fecha_inicio')->nullable(); $table->date('fecha_fin')->nullable();
            $table->decimal('valor_total', 18, 2)->nullable(); $table->json('detalle')->nullable(); $table->timestamps();
        });
    }

    public function test_separa_personas_unicas_de_cantidad_de_registros(): void
    {
        $lote = SecopAuditoriaLote::create(['anio' => 2026, 'criterio_vigencia' => 'ejecucion_superpuesta']);
        foreach ([[1, 'solo_secop'], [1, 'solo_secop'], [2, 'solo_secop'], [1, 'requiere_revision']] as [$persona, $tipo]) {
            SecopAuditoriaHallazgo::create(['lote_id' => $lote->id, 'persona_id' => $persona, 'tipo' => $tipo]);
        }

        $metrics = $lote->metricasPorTipo();

        $this->assertSame(['personas' => 2, 'registros' => 3], $metrics['solo_secop']);
        $this->assertSame(['personas' => 1, 'registros' => 1], $metrics['requiere_revision']);
        $this->assertSame(['personas' => 0, 'registros' => 0], $metrics['alerta_secop']);
    }
}
