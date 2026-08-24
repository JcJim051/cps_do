<?php

namespace Tests\Feature;

use App\Models\Estados;
use App\Models\GoogleIntegration;
use App\Models\PrevalidacionContractual;
use App\Models\PrevalidacionFuente;
use App\Models\Seguimiento;
use App\Services\GoogleSheetsService;
use App\Services\PrevalidacionPromocionService;
use App\Services\PrevalidacionSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrevalidacionContractualTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('estados', fn (Blueprint $t) => $this->basic($t, ['nombre']));
        Schema::create('estado_personas', fn (Blueprint $t) => $this->basic($t, ['nombre']));
        Schema::create('tipos', fn (Blueprint $t) => $this->basic($t, ['nombre']));
        Schema::create('personas', function (Blueprint $t) {
            $t->id(); $t->string('nombre_contratista'); $t->string('cedula_o_nit')->unique();
            $t->unsignedBigInteger('secretaria_id')->nullable(); $t->unsignedBigInteger('gerencia_id')->nullable();
            $t->unsignedBigInteger('estado_persona_id')->nullable(); $t->unsignedBigInteger('tipos_id')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable(); $t->timestamps();
        });
        Schema::create('gerencias', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->unsignedBigInteger('secretaria_id'); $t->timestamps();
        });
        Schema::create('prevalidacion_fuentes', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->unsignedBigInteger('secretaria_id')->nullable(); $t->string('nit_entidad')->nullable();
            $t->string('spreadsheet_id'); $t->string('spreadsheet_url')->nullable(); $t->string('hoja'); $t->integer('fila_encabezados')->default(1);
            $t->integer('anio_objetivo')->nullable();
            $t->json('mapeo_columnas'); $t->string('columna_clave')->nullable(); $t->string('columna_estado'); $t->string('columna_editado');
            $t->boolean('activa')->default(true); $t->timestamp('ultima_sincronizacion_at')->nullable(); $t->text('ultimo_error')->nullable(); $t->timestamps();
        });
        Schema::create('google_integrations', function (Blueprint $t) {
            $t->id(); $t->string('account_email')->nullable(); $t->text('oauth_client_id')->nullable();
            $t->text('oauth_client_secret')->nullable(); $t->text('access_token')->nullable(); $t->text('refresh_token')->nullable();
            $t->timestamp('token_expires_at')->nullable(); $t->json('scopes')->nullable(); $t->timestamp('connected_at')->nullable(); $t->timestamps();
        });
        Schema::create('seguimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('persona_id'); $t->string('tipo');
            $t->unsignedBigInteger('secretaria_id')->nullable(); $t->unsignedBigInteger('gerencia_id')->nullable();
            $t->unsignedBigInteger('estado_contrato_id')->nullable(); $t->integer('anio')->nullable();
            $t->string('numero_contrato')->nullable(); $t->date('fecha_acta_inicio')->nullable(); $t->date('fecha_finalizacion')->nullable();
            $t->integer('tiempo_ejecucion_dias')->nullable(); $t->decimal('valor_mensual', 15, 2)->nullable();
            $t->decimal('valor_total', 15, 2)->nullable(); $t->decimal('valor_total_contrato', 15, 2)->nullable();
            $t->boolean('aut_despacho')->default(false); $t->date('fecha_aut_despacho')->nullable();
            $t->string('adicion')->nullable(); $t->date('fecha_acta_inicio_adicion')->nullable();
            $t->date('fecha_finalizacion_adicion')->nullable(); $t->integer('tiempo_ejecucion_dias_adicion')->nullable();
            $t->integer('tiempo_total_ejecucion_dias')->nullable(); $t->decimal('valor_adicion', 15, 2)->nullable();
            $t->text('observaciones_contrato')->nullable(); $t->timestamps();
        });
        Schema::create('prevalidaciones_contractuales', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('fuente_id')->nullable(); $t->string('clave_externa'); $t->integer('fila_origen')->nullable();
            $t->string('numero_origen')->nullable(); $t->string('cedula_o_nit'); $t->string('nombre_contratista')->nullable();
            $t->text('nombre_reportado')->nullable(); $t->boolean('nombre_requiere_revision')->default(false); $t->unsignedBigInteger('persona_id')->nullable();
            $t->unsignedBigInteger('secretaria_id')->nullable(); $t->unsignedBigInteger('gerencia_id')->nullable();
            $t->string('dependencia_origen')->nullable(); $t->string('programa_origen')->nullable(); $t->string('celular')->nullable();
            $t->string('nivel_academico')->nullable(); $t->text('profesion')->nullable(); $t->text('especializacion')->nullable();
            $t->text('maestria')->nullable(); $t->string('fuente_recursos')->nullable(); $t->string('estado');
            $t->string('estado_origen')->nullable(); $t->string('etapa')->default('POR_GESTIONAR');
            $t->integer('anio')->nullable(); $t->string('numero_contrato_planeado')->nullable();
            $t->date('fecha_inicio_planeada')->nullable(); $t->date('fecha_fin_planeada')->nullable(); $t->integer('tiempo_planeado_dias')->nullable();
            $t->decimal('valor_mensual_planeado', 15, 2)->nullable(); $t->decimal('valor_total_planeado', 15, 2)->nullable();
            $t->string('adicion')->nullable(); $t->date('fecha_inicio_adicion')->nullable(); $t->date('fecha_fin_adicion')->nullable();
            $t->integer('tiempo_adicion_dias')->nullable(); $t->integer('tiempo_total_dias')->nullable();
            $t->decimal('valor_adicion',15,2)->nullable(); $t->decimal('valor_total_contrato',15,2)->nullable();
            $t->string('evaluacion')->nullable(); $t->string('continua')->nullable(); $t->text('observaciones')->nullable();
            $t->text('sector')->nullable(); $t->text('enlace_origen')->nullable(); $t->timestamp('aprobado_at')->nullable(); $t->unsignedBigInteger('aprobado_por')->nullable();
            $t->unsignedBigInteger('seguimiento_id')->nullable(); $t->timestamp('promovido_at')->nullable(); $t->unsignedBigInteger('promovido_por')->nullable();
            $t->unsignedBigInteger('drive_protected_range_id')->nullable(); $t->timestamp('drive_protegido_at')->nullable();
            $t->boolean('drive_sincronizacion_pendiente')->default(false); $t->text('drive_ultimo_error')->nullable();
            $t->json('datos_origen')->nullable(); $t->json('campos_locales')->nullable(); $t->json('conflictos')->nullable(); $t->string('hash_origen')->nullable();
            $t->boolean('estado_gestionado_localmente')->default(false); $t->boolean('editado_en_integra')->default(false);
            $t->boolean('presente_en_origen')->default(true); $t->timestamp('ultima_sincronizacion_at')->nullable(); $t->timestamps();
        });
        Schema::create('prevalidacion_eventos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('prevalidacion_id'); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('tipo'); $t->json('antes')->nullable(); $t->json('despues')->nullable(); $t->text('detalle')->nullable(); $t->timestamps();
        });
        Schema::create('secop_vinculos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('prevalidacion_id')->nullable(); $t->unsignedBigInteger('seguimiento_id')->nullable(); $t->timestamps();
        });

        DB::table('estados')->insert([['nombre' => 'APROBADO'], ['nombre' => 'PENDIENTE APROBACIÓN'], ['nombre' => 'CAMBIO']]);
        DB::table('estado_personas')->insert(['nombre' => 'EN PROCESO']);
        DB::table('tipos')->insert(['nombre' => 'Cps']);
    }

    public function test_aprobado_activa_despacho_y_pendiente_lo_retira(): void
    {
        DB::table('personas')->insert(['id' => 1, 'nombre_contratista' => 'Persona', 'cedula_o_nit' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $approved = Estados::where('nombre', 'APROBADO')->firstOrFail();
        $pending = Estados::where('nombre', 'PENDIENTE APROBACIÓN')->firstOrFail();

        $tracking = Seguimiento::create(['persona_id' => 1, 'tipo' => 'contrato', 'estado_contrato_id' => $approved->id]);
        $this->assertTrue($tracking->fresh()->aut_despacho);
        $this->assertNotNull($tracking->fresh()->fecha_aut_despacho);

        $tracking->estado_contrato_id = $pending->id;
        $tracking->save();
        $this->assertFalse($tracking->fresh()->aut_despacho);
        $this->assertNull($tracking->fresh()->fecha_aut_despacho);
    }

    public function test_promocion_crea_persona_y_seguimiento_una_sola_vez(): void
    {
        $record = PrevalidacionContractual::create([
            'fuente_id' => 1, 'clave_externa' => 'aim-1', 'cedula_o_nit' => '123456',
            'nombre_contratista' => 'Persona Nueva', 'secretaria_id' => 27, 'estado' => 'APROBADO',
            'anio' => 2026, 'valor_total_planeado' => 12000000, 'aprobado_at' => now()->subDay(),
        ]);

        $sync = $this->mock(PrevalidacionSyncService::class);
        $sync->shouldReceive('assertReadyForTracking')->once();
        $sync->shouldReceive('moveToTracking')->twice();
        $service = new PrevalidacionPromocionService($sync);
        $first = $service->promover($record, null);
        $second = $service->promover($record->fresh(), null);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('seguimientos', 1);
        $this->assertTrue($first->fresh()->aut_despacho);
        $this->assertSame('123456', $first->persona->cedula_o_nit);
    }

    public function test_paso_a_seguimiento_localiza_por_id_cambia_estado_y_protege_fila(): void
    {
        $source = PrevalidacionFuente::create([
            'nombre' => 'AIM', 'spreadsheet_id' => 'sheet', 'hoja' => 'CPS', 'mapeo_columnas' => [],
            'columna_clave' => 'ID_INTEGRA', 'columna_estado' => 'ESTADO', 'columna_editado' => 'EDITADO_EN_INTEGRA', 'activa' => true,
        ]);
        $record = PrevalidacionContractual::create([
            'fuente_id' => $source->id, 'clave_externa' => '123-2026-01', 'cedula_o_nit' => '123',
            'fila_origen' => 3, 'estado' => 'APROBADO', 'estado_origen' => 'APROBADO', 'seguimiento_id' => 99,
        ]);
        $google = $this->mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->once()->andReturn([
            ['NOMBRE', 'ESTADO', 'ID_INTEGRA'], ['Otra', 'VALIDACION HV', '123-2026-01'], ['Persona', 'APROBADO', '123-2026-01'],
        ]);
        $google->shouldReceive('sheetMetadata')->once()->with('sheet', 'CPS', 3)->andReturn([
            'data' => [['rowData' => [['values' => [[], ['dataValidation' => ['condition' => ['type' => 'ONE_OF_LIST', 'values' => [['userEnteredValue' => 'APROBADO']]], 'strict' => true]]]]]]],
        ]);
        $google->shouldReceive('transitionAndProtectRow')->once()
            ->withArgs(fn ($sheetId, $tab, $row, $statusColumn, $editedColumn, $status, $description) =>
                $sheetId === 'sheet' && $tab === 'CPS' && $row === 3 && $statusColumn === 1 && $editedColumn === 3
                && $status === 'EN CONTRATACIÓN' && $description === 'INTEGRA:123-2026-01')
            ->andReturn(777);

        (new PrevalidacionSyncService($google))->moveToTracking($record, null);

        $record->refresh();
        $this->assertSame('EN CONTRATACIÓN', $record->estado_origen);
        $this->assertSame(777, $record->drive_protected_range_id);
        $this->assertFalse($record->drive_sincronizacion_pendiente);
        $this->assertFalse($record->presente_en_origen);
    }

    public function test_no_crea_seguimiento_si_drive_ya_no_esta_aprobado(): void
    {
        $record = PrevalidacionContractual::create([
            'fuente_id' => 1, 'clave_externa' => 'aim-cambio', 'cedula_o_nit' => '654321',
            'nombre_contratista' => 'Persona Cambiada', 'secretaria_id' => 27, 'estado' => 'APROBADO',
        ]);
        $sync = $this->mock(PrevalidacionSyncService::class);
        $sync->shouldReceive('assertReadyForTracking')->once()->andThrow(
            new \RuntimeException('La fila cambió en Drive a VALIDACION HV; no se creó ni sobrescribió el seguimiento.')
        );
        $sync->shouldNotReceive('moveToTracking');

        $this->expectException(\RuntimeException::class);
        try {
            (new PrevalidacionPromocionService($sync))->promover($record, null);
        } finally {
            $this->assertDatabaseCount('seguimientos', 0);
            $this->assertNull($record->fresh()->seguimiento_id);
        }
    }

    public function test_sincronizacion_es_idempotente_y_respeta_campos_locales(): void
    {
        $source = PrevalidacionFuente::create([
            'nombre' => 'AIM', 'secretaria_id' => 27, 'nit_entidad' => '123', 'spreadsheet_id' => 'sheet', 'hoja' => 'CPS',
            'anio_objetivo' => 2026,
            'mapeo_columnas' => ['cedula_o_nit' => 'CEDULA', 'nombre_contratista' => 'NOMBRE', 'estado' => 'ESTADO', 'anio' => 'AÑO', 'valor_total' => 'VALOR', 'fecha_inicio' => 'INICIO'],
            'columna_estado' => 'ESTADO', 'columna_editado' => 'EDITADO_EN_INTEGRA', 'activa' => true,
        ]);
        $google = $this->mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->twice()->andReturn(
            [['CEDULA', 'NOMBRE', 'ESTADO', 'AÑO', 'VALOR', 'INICIO'], ['123456', 'Persona Nueva', 'APROBADO', '2026', '100000', '31/01/2026'], ['888', 'No aprobada', 'PENDIENTE', '2026', '1', '1/1/2026'], ['999', 'Histórica', 'APROBADO', '2025', '1', '1/1/2025']],
            [['CEDULA', 'NOMBRE', 'ESTADO', 'AÑO', 'VALOR', 'INICIO'], ['123456', 'Persona Nueva', 'APROBADO', '2026', '200000', '31/01/2026'], ['888', 'No aprobada', 'PENDIENTE', '2026', '1', '1/1/2026'], ['999', 'Histórica', 'APROBADO', '2025', '1', '1/1/2025']],
        );
        $google->shouldReceive('updateCells')->twice();
        $google->shouldReceive('protectColumnsWithWarning')->twice();
        $service = new PrevalidacionSyncService($google);
        $service->sincronizar($source);
        $record = PrevalidacionContractual::firstOrFail();
        $record->valor_total_planeado = 150000;
        $record->campos_locales = ['valor_total_planeado'];
        $record->save();

        $service->sincronizar($source->fresh());
        $record->refresh();

        $this->assertDatabaseCount('prevalidaciones_contractuales', 1);
        $this->assertSame('123456-2026-01', $record->clave_externa);
        $this->assertSame('2026-01-31', $record->fecha_inicio_planeada->toDateString());
        $this->assertSame(150000.0, (float) $record->valor_total_planeado);
        $this->assertArrayHasKey('valor_total_planeado', $record->conflictos);
    }

    public function test_credenciales_google_se_guardan_cifradas(): void
    {
        GoogleIntegration::create([
            'oauth_client_id' => 'cliente.apps.googleusercontent.com',
            'oauth_client_secret' => 'secreto-google',
        ]);

        $stored = DB::table('google_integrations')->first();

        $this->assertNotSame('cliente.apps.googleusercontent.com', $stored->oauth_client_id);
        $this->assertNotSame('secreto-google', $stored->oauth_client_secret);
        $this->assertSame('cliente.apps.googleusercontent.com', GoogleIntegration::find(1)->oauth_client_id);
        $this->assertSame('secreto-google', GoogleIntegration::find(1)->oauth_client_secret);
    }

    private function basic(Blueprint $table, array $strings): void
    {
        $table->id();
        foreach ($strings as $field) $table->string($field);
        $table->timestamps();
    }
}
