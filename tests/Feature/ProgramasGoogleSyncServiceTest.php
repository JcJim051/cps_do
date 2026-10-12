<?php

namespace Tests\Feature;

use App\Models\Programa;
use App\Models\ProgramaFuente;
use App\Services\GoogleSheetsService;
use App\Services\ProgramasGoogleSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ProgramasGoogleSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('operador')->nullable();
            $table->string('perfil')->nullable();
            $table->string('municipio')->nullable();
            $table->string('institucion')->nullable();
            $table->string('sede')->nullable();
            $table->string('nombre_apellidos')->nullable();
            $table->string('cedula')->nullable();
            $table->string('contacto')->nullable();
            $table->string('ref_1')->nullable();
            $table->string('ref_2')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedInteger('fila_origen')->nullable();
            $table->string('origen')->default('manual');
            $table->boolean('presente_en_origen')->default(true);
            $table->string('hash_origen')->nullable();
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });

        Schema::create('programa_fuentes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('spreadsheet_id');
            $table->string('spreadsheet_url');
            $table->string('hoja');
            $table->unsignedInteger('fila_encabezados')->default(1);
            $table->boolean('activa')->default(true);
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->json('ultimo_resumen')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
        });

        ProgramaFuente::query()->create([
            'nombre' => 'Prueba',
            'spreadsheet_id' => 'sheet-test',
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/sheet-test',
            'hoja' => 'CONSOLIDADO GENERAL',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('programa_fuentes');
        Schema::dropIfExists('programas');
        parent::tearDown();
    }

    public function test_it_syncs_assignments_idempotently_and_inactivates_missing_rows(): void
    {
        $fuente = ProgramaFuente::query()->firstOrFail();
        Programa::query()->create([
            'external_id' => 'REMOVED-1',
            'operador' => 'ANTERIOR',
            'municipio' => 'Granada',
            'nombre_apellidos' => 'REGISTRO RETIRADO',
            'estado' => 'VIGENTE',
            'origen' => 'google_sheets',
            'presente_en_origen' => true,
        ]);

        $rows = [
            ['MUNICIPIO', 'OPERADOR', 'PERFIL', 'INSTITUCION EDUCATIVA Y/O SEDE', 'NOMBRE COMPLETO', 'CEDULA', 'CONTACTO', 'REFERENCIA 1', 'REFERENCIA 2', 'ESTADO', 'ID_INTEGRA'],
            ['Acacías', 'PAE', 'MANIPULADORA', 'IE CENTRAL', 'ANA PEREZ', '123', null, null, null, 'VIGENTE', 'PRG-1'],
            ['Acacías', 'CENTROS VIDA', 'TECNICO', 'N/A', 'ANA PEREZ', '123', null, null, null, 'VIGENTE', 'PRG-2'],
            ['Granada', 'IDERMETA', 'PROFESIONAL', 'N/A', 'PERSONA SIN DOCUMENTO', null, null, null, null, 'VIGENTE', 'PRG-3'],
            ['VISTAHERMOSA', 'PAE', 'MANIPULADORA', 'IE CENTRAL', 'MUNICIPIO INVALIDO', '999', null, null, null, 'VIGENTE', 'PRG-4'],
            ['Granada', 'PAE', 'MANIPULADORA', 'IE CENTRAL', null, '888', null, null, null, 'VIGENTE', 'PRG-5'],
        ];

        $google = Mockery::mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->twice()->andReturn($rows);
        $google->shouldReceive('protectColumnsWithWarning')->twice()->andReturn(['ID_INTEGRA' => 1]);

        $service = new ProgramasGoogleSyncService($google);
        $first = $service->sincronizar($fuente);
        $second = $service->sincronizar($fuente->fresh());

        $this->assertSame(3, $first['creados']);
        $this->assertSame(1, $first['inactivados']);
        $this->assertCount(2, $first['errores']);
        $this->assertSame(0, $second['creados']);
        $this->assertSame(3, $second['actualizados']);
        $this->assertSame(4, Programa::query()->count());
        $this->assertFalse(Programa::query()->where('external_id', 'REMOVED-1')->firstOrFail()->presente_en_origen);
        $this->assertDatabaseHas('programas', [
            'external_id' => 'PRG-2',
            'cedula' => '123',
            'operador' => 'CENTROS VIDA',
            'presente_en_origen' => true,
        ]);
        $this->assertDatabaseHas('programas', [
            'external_id' => 'PRG-3',
            'cedula' => null,
            'presente_en_origen' => true,
        ]);
    }

    public function test_it_generates_and_writes_an_external_id_for_a_valid_new_row(): void
    {
        $fuente = ProgramaFuente::query()->firstOrFail();
        $rows = [
            ['MUNICIPIO', 'OPERADOR', 'PERFIL', 'INSTITUCION EDUCATIVA Y/O SEDE', 'NOMBRE COMPLETO', 'CEDULA', 'CONTACTO', 'REFERENCIA 1', 'REFERENCIA 2', 'ESTADO'],
            ['Granada', 'PAE', 'MANIPULADORA', 'IE CENTRAL', 'NUEVA PERSONA', null, null, null, null, 'VIGENTE'],
        ];

        $google = Mockery::mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->once()->andReturn($rows);
        $google->shouldReceive('updateCell')->once()->with($fuente->spreadsheet_id, $fuente->hoja, 1, 10, 'ID_INTEGRA');
        $google->shouldReceive('updateCells')->once()->withArgs(function ($spreadsheetId, $updates) use ($fuente) {
            return $spreadsheetId === $fuente->spreadsheet_id
                && count($updates) === 1
                && $updates[0]['row'] === 2
                && $updates[0]['column'] === 10
                && str_starts_with($updates[0]['value'], 'PRG-');
        });
        $google->shouldReceive('protectColumnsWithWarning')->once()->andReturn(['ID_INTEGRA' => 1]);

        $result = (new ProgramasGoogleSyncService($google))->sincronizar($fuente);

        $this->assertSame(1, $result['ids_generados']);
        $this->assertSame(1, $result['creados']);
        $this->assertDatabaseCount('programas', 1);
        $this->assertStringStartsWith('PRG-', Programa::query()->firstOrFail()->external_id);
    }

    public function test_it_rejects_duplicate_ids_and_reactivates_a_returning_assignment(): void
    {
        $fuente = ProgramaFuente::query()->firstOrFail();
        Programa::query()->create([
            'external_id' => 'RETURNING-1',
            'operador' => 'PAE',
            'municipio' => 'Granada',
            'nombre_apellidos' => 'PERSONA QUE REGRESA',
            'origen' => 'google_sheets',
            'presente_en_origen' => false,
        ]);

        $rows = [
            ['MUNICIPIO', 'OPERADOR', 'PERFIL', 'INSTITUCION EDUCATIVA Y/O SEDE', 'NOMBRE COMPLETO', 'CEDULA', 'CONTACTO', 'REFERENCIA 1', 'REFERENCIA 2', 'ESTADO', 'ID_INTEGRA'],
            ['Granada', 'PAE', 'MANIPULADORA', 'IE CENTRAL', 'PERSONA QUE REGRESA', null, null, null, null, 'VIGENTE', 'RETURNING-1'],
            ['Granada', 'PAE', 'MANIPULADORA', 'OTRA SEDE', 'DUPLICADO', '456', null, null, null, 'VIGENTE', 'RETURNING-1'],
        ];

        $google = Mockery::mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->once()->andReturn($rows);
        $google->shouldReceive('protectColumnsWithWarning')->once()->andReturn(['ID_INTEGRA' => 1]);

        $result = (new ProgramasGoogleSyncService($google))->sincronizar($fuente);

        $this->assertCount(1, $result['errores']);
        $this->assertStringContainsString('ID_INTEGRA duplicado', $result['errores'][0]);
        $this->assertDatabaseCount('programas', 1);
        $this->assertTrue(Programa::query()->where('external_id', 'RETURNING-1')->firstOrFail()->presente_en_origen);
    }

    public function test_dry_run_reports_missing_values_without_writing_anything(): void
    {
        $fuente = ProgramaFuente::query()->firstOrFail();
        $rows = [
            ['MUNICIPIO', 'OPERADOR', 'PERFIL', 'INSTITUCION EDUCATIVA Y/O SEDE', 'NOMBRE COMPLETO', 'CEDULA', 'CONTACTO', 'REFERENCIA 1', 'REFERENCIA 2', 'ESTADO', 'ID_INTEGRA'],
            ['Granada', 'PAE', 'MANIPULADORA', 'IE CENTRAL', 'PERSONA VALIDA', null, null, null, null, 'VIGENTE', null],
            ['Granada', null, 'MANIPULADORA', 'IE CENTRAL', 'PERSONA INCOMPLETA', null, null, null, null, 'VIGENTE', null],
        ];

        $google = Mockery::mock(GoogleSheetsService::class);
        $google->shouldReceive('values')->once()->andReturn($rows);

        $result = (new ProgramasGoogleSyncService($google))->sincronizar($fuente, true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['validas']);
        $this->assertSame(1, $result['ids_generados']);
        $this->assertCount(1, $result['errores']);
        $this->assertDatabaseCount('programas', 0);
        $this->assertNull($fuente->fresh()->ultima_sincronizacion_at);
    }

    public function test_it_reports_missing_headers_and_google_errors(): void
    {
        $fuente = ProgramaFuente::query()->firstOrFail();
        $missingHeaders = Mockery::mock(GoogleSheetsService::class);
        $missingHeaders->shouldReceive('values')->once()->andReturn([
            ['MUNICIPIO', 'OPERADOR', 'NOMBRE COMPLETO'],
        ]);

        try {
            (new ProgramasGoogleSyncService($missingHeaders))->sincronizar($fuente);
            $this->fail('La sincronización debía fallar por encabezados faltantes.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Faltan columnas obligatorias', $e->getMessage());
        }

        $googleFailure = Mockery::mock(GoogleSheetsService::class);
        $googleFailure->shouldReceive('values')->once()->andThrow(new \RuntimeException('Google no disponible'));

        try {
            (new ProgramasGoogleSyncService($googleFailure))->sincronizar($fuente->fresh());
            $this->fail('La sincronización debía propagar el error de Google.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Google no disponible', $e->getMessage());
        }

        $this->assertSame('Google no disponible', $fuente->fresh()->ultimo_error);
    }
}
