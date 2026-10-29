<?php

namespace Tests\Feature;

use App\Exports\ProgramasAnalysisExport;
use App\Http\Controllers\Admin\ProgramasDashboardController;
use App\Models\Programa;
use App\Models\User;
use App\Services\ProgramasAnalysisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProgramasAnalysisExportTest extends TestCase
{
    private string $originalDatabaseConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabaseConnection = (string) config('database.default');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('programas');
        DB::purge('sqlite');
        config(['database.default' => $this->originalDatabaseConnection]);
        parent::tearDown();
    }

    public function test_filters_and_search_are_shared_with_export_analysis(): void
    {
        $this->programa('PRG-1', 'ANA PÉREZ', '00123', 'PAE', 'Acacías', 'ALCALDE', 'ZONA 1');
        $this->programa('PRG-2', 'ANA PÉREZ', '00123', 'PAE', 'Acacías', 'ALCALDE', 'ZONA 2');
        $this->programa('PRG-3', 'OTRA PERSONA', '999', 'CULTURA', 'Acacías', 'CONCEJAL', 'ZONA 1');
        $this->programa('PRG-4', 'ANA RETIRADA', '777', 'PAE', 'Acacías', 'ALCALDE', 'ZONA 1', 'RETIRADO');
        $this->programa('PRG-5', 'ANA AUSENTE', '888', 'PAE', 'Acacías', 'ALCALDE', 'ZONA 1', 'VIGENTE', false);

        $service = app(ProgramasAnalysisService::class);
        $dataset = $service->resolve([
            'municipio' => 'Acacías',
            'operador' => 'PAE',
            'perfil' => 'DOCENTE',
            'estado' => 'VIGENTE',
            'referencia_1' => 'ALCALDE',
            'buscar' => 'ana pérez',
        ]);
        $summary = $service->summarize($dataset['detailRows']);

        $this->assertCount(2, $dataset['analysisRows']);
        $this->assertCount(2, $dataset['detailRows']);
        $this->assertSame(1, $summary['metrics']['personas']);
        $this->assertSame(2, $summary['metrics']['asignaciones']);
        $this->assertSame(1, $summary['metrics']['proyectos']);
        $this->assertSame(2, $summary['metrics']['referencias_2']);

        $reference2 = $service->resolve(['estado' => 'VIGENTE', 'referencia_2' => 'ZONA 2']);
        $this->assertSame(['PRG-2'], $reference2['detailRows']->pluck('external_id')->all());

        $retired = $service->resolve(['estado' => 'RETIRADO']);
        $this->assertSame(['PRG-4'], $retired['detailRows']->pluck('external_id')->all());

        $allRows = $service->resolve(['estado' => 'TODOS'])['analysisRows'];
        $this->assertSame(['PRG-1', 'PRG-2'], $service->segment($allRows, 'operador', 'PAE')->where('estado', 'VIGENTE')->pluck('external_id')->all());
        $this->assertCount(4, $service->segment($allRows, 'municipio', 'Acacías'));
        $this->assertCount(3, $service->segment($allRows, 'referencia_1', 'ALCALDE'));
        $this->assertCount(3, $service->segment($allRows, 'referencia_2', 'ZONA 1'));
        $this->assertSame(['PRG-3'], $service->search($allRows, 'OTRA PERSONA')->pluck('external_id')->all());
        $this->assertTrue($service->segment($allRows, 'dimension_invalida', 'PAE')->isEmpty());
    }

    public function test_workbook_contains_six_formatted_sheets_and_preserves_text(): void
    {
        $this->programa('PRG-001', '=2+2', '00123', 'PAE', 'Acacías', 'ALCALDE', 'ZONA 1', contacto: '0300123456');

        $service = app(ProgramasAnalysisService::class);
        $dataset = $service->resolve(['estado' => 'VIGENTE']);
        $summary = $service->summarize($dataset['detailRows']);
        $export = new ProgramasAnalysisExport(
            $dataset['detailRows'],
            $dataset['filters'],
            $summary,
            now('America/Bogota')
        );

        $path = tempnam(sys_get_temp_dir(), 'programas-export-');
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));
        $workbook = IOFactory::load($path);

        $this->assertSame(
            ['Resumen', 'Datos', 'Por municipio', 'Por proyecto', 'Referencia 1', 'Referencia 2'],
            $workbook->getSheetNames()
        );
        $data = $workbook->getSheetByName('Datos');
        $this->assertSame('ID INTEGRA', $data->getCell('A1')->getValue());
        $this->assertSame('PRG-001', $data->getCell('A2')->getValue());
        $this->assertSame('=2+2', $data->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $data->getCell('B2')->getDataType());
        $this->assertSame('00123', $data->getCell('C2')->getValue());
        $this->assertSame('0300123456', $data->getCell('D2')->getValue());
        $this->assertSame('A2', $data->getFreezePane());
        $this->assertSame('A1:K2', $data->getAutoFilter()->getRange());

        @unlink($path);
    }

    public function test_empty_result_still_generates_a_valid_workbook(): void
    {
        $service = app(ProgramasAnalysisService::class);
        $dataset = $service->resolve(['estado' => 'VIGENTE', 'buscar' => 'NO EXISTE']);
        $summary = $service->summarize($dataset['detailRows']);
        $export = new ProgramasAnalysisExport(
            $dataset['detailRows'],
            $dataset['filters'],
            $summary,
            now('America/Bogota')
        );

        $path = tempnam(sys_get_temp_dir(), 'programas-empty-export-');
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));
        $workbook = IOFactory::load($path);

        $this->assertCount(6, $workbook->getAllSheets());
        $this->assertSame('ID INTEGRA', $workbook->getSheetByName('Datos')->getCell('A1')->getValue());
        $this->assertSame(0, $summary['metrics']['asignaciones']);

        @unlink($path);
    }

    public function test_export_route_is_protected_by_backpack(): void
    {
        $route = Route::getRoutes()->getByName('programas.export');

        $this->assertNotNull($route);
        $this->assertSame('admin/programas/dashboard/exportar', $route->uri());
        $this->assertContains('web', $route->middleware());
        $this->assertContains(config('backpack.base.middleware_key', 'admin'), $route->middleware());

        $detailRoute = Route::getRoutes()->getByName('programas.detail');
        $this->assertNotNull($detailRoute);
        $this->assertSame('admin/programas/dashboard/detalle', $detailRoute->uri());
        $this->assertContains('web', $detailRoute->middleware());
        $this->assertContains(config('backpack.base.middleware_key', 'admin'), $detailRoute->middleware());
    }

    public function test_chart_detail_is_paginated_searchable_and_available_to_module_roles(): void
    {
        foreach (range(1, 30) as $index) {
            $this->programa(
                'DETAIL-'.$index,
                'PERSONA '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                $index <= 2 ? '100' : (string) (100 + $index),
                'PROYECTO DETALLE',
                'Acacías',
                'ALCALDE',
                'ZONA 1'
            );
        }

        foreach ([1, 2, 8] as $roleId) {
            $this->authenticateRole($roleId);
            $response = app(ProgramasDashboardController::class)->detail(
                Request::create('/admin/programas/dashboard/detalle', 'GET', [
                    'dimension' => 'operador',
                    'valor' => 'PROYECTO DETALLE',
                    'estado' => 'VIGENTE',
                    'page' => 1,
                ]),
                app(ProgramasAnalysisService::class)
            );
            $payload = $response->getData(true);

            $this->assertSame(30, $payload['asignaciones']);
            $this->assertSame(29, $payload['personas']);
            $this->assertCount(25, $payload['rows']);
            $this->assertSame(2, $payload['pagination']['last_page']);
        }

        $searchResponse = app(ProgramasDashboardController::class)->detail(
            Request::create('/admin/programas/dashboard/detalle', 'GET', [
                'dimension' => 'referencia_1',
                'valor' => 'ALCALDE',
                'estado' => 'VIGENTE',
                'buscar' => 'PERSONA 29',
            ]),
            app(ProgramasAnalysisService::class)
        )->getData(true);

        $this->assertSame(1, $searchResponse['asignaciones']);
        $this->assertSame('PERSONA 29', $searchResponse['rows'][0]['persona']);

        $secondPage = app(ProgramasDashboardController::class)->detail(
            Request::create('/admin/programas/dashboard/detalle', 'GET', [
                'dimension' => 'operador',
                'valor' => 'PROYECTO DETALLE',
                'estado' => 'VIGENTE',
                'page' => 2,
            ]),
            app(ProgramasAnalysisService::class)
        )->getData(true);
        $this->assertSame(2, $secondPage['pagination']['page']);
        $this->assertCount(5, $secondPage['rows']);

        $empty = app(ProgramasDashboardController::class)->detail(
            Request::create('/admin/programas/dashboard/detalle', 'GET', [
                'dimension' => 'municipio',
                'valor' => 'Municipio inexistente',
                'estado' => 'VIGENTE',
            ]),
            app(ProgramasAnalysisService::class)
        )->getData(true);
        $this->assertSame(0, $empty['asignaciones']);
        $this->assertSame([], $empty['rows']);
    }

    public function test_chart_detail_rejects_invalid_dimensions(): void
    {
        $this->authenticateRole(1);

        $this->expectException(ValidationException::class);
        app(ProgramasDashboardController::class)->detail(
            Request::create('/admin/programas/dashboard/detalle', 'GET', [
                'dimension' => 'campo_invalido',
                'valor' => 'PAE',
            ]),
            app(ProgramasAnalysisService::class)
        );
    }

    public function test_export_rejects_a_user_without_module_access(): void
    {
        backpack_auth()->logout();

        try {
            app(\App\Http\Controllers\Admin\ProgramasDashboardController::class)->export(
                request(),
                app(ProgramasAnalysisService::class)
            );
            $this->fail('La exportación debía rechazar al usuario sin permisos.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function programa(
        string $externalId,
        string $nombre,
        ?string $cedula,
        string $operador,
        string $municipio,
        string $referencia1,
        string $referencia2,
        string $estado = 'VIGENTE',
        bool $presente = true,
        string $contacto = '3001234567',
    ): Programa {
        return Programa::query()->create([
            'external_id' => $externalId,
            'operador' => $operador,
            'perfil' => 'DOCENTE',
            'municipio' => $municipio,
            'institucion' => 'IE CENTRAL',
            'nombre_apellidos' => $nombre,
            'cedula' => $cedula,
            'contacto' => $contacto,
            'ref_1' => $referencia1,
            'ref_2' => $referencia2,
            'estado' => $estado,
            'origen' => 'google_sheets',
            'presente_en_origen' => $presente,
        ]);
    }

    private function authenticateRole(int $roleId): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['role_id' => $roleId]);
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        backpack_auth()->setUser($user);
    }
}
