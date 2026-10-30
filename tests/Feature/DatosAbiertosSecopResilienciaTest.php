<?php

namespace Tests\Feature;

use App\Services\DatosAbiertosSecopService;
use App\Services\SecopNormalizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatosAbiertosSecopResilienciaTest extends TestCase
{
    public function test_consulta_manual_conserva_secop_ii_si_secop_i_falla(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'jbjy-vk9h')) {
                return Http::response([[
                    'id_contrato' => 'CO1.PCCNTR.8933158',
                    'referencia_del_contrato' => 'CONTRATO 059 DE 2026',
                    'estado_contrato' => 'Modificado',
                    'documento_proveedor' => '1030590916',
                ]]);
            }

            return Http::response(['message' => 'Temporalmente no disponible'], 503);
        });

        $service = new DatosAbiertosSecopService(new SecopNormalizer());
        $result = $service->consultarPorDocumento('1030590916');

        $this->assertCount(1, $result);
        $this->assertSame('CO1.PCCNTR.8933158', $result[0]['identificador_externo']);
        $this->assertSame(
            ['SECOP I no respondió; se muestran los resultados disponibles de la otra fuente.'],
            $service->warnings(),
        );
    }

    public function test_consulta_envia_token_de_aplicacion_socrata(): void
    {
        config(['services.socrata.app_token' => 'token-prueba']);
        Http::fake(fn () => Http::response([]));

        (new DatosAbiertosSecopService(new SecopNormalizer()))
            ->consultarPorDocumento('1030590916');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-App-Token', 'token-prueba'));
    }

    public function test_contrato_secop_dos_se_complementa_con_codigo_y_nombre_de_unidad(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'jbjy-vk9h')) {
                return Http::response([[
                    'id_contrato' => 'CO1.PCCNTR.1',
                    'proceso_de_compra' => 'CO1.REQ.1',
                    'referencia_del_contrato' => '59-2026',
                    'documento_proveedor' => '123',
                ]]);
            }
            if (str_contains($request->url(), 'p6dx-8zbt')) {
                return Http::response([[
                    'id_del_proceso' => 'CO1.REQ.1',
                    'ppi' => 'PCI-SALUD',
                    'nombre_de_la_unidad_de' => 'SECRETARÍA DE SALUD',
                ]]);
            }
            return Http::response([]);
        });

        $result = (new DatosAbiertosSecopService(new SecopNormalizer()))->consultarPorDocumento('123');

        $this->assertSame('PCI-SALUD', $result[0]['codigo_dependencia']);
        $this->assertSame('SECRETARÍA DE SALUD', $result[0]['nombre_dependencia']);
    }

    public function test_contrato_directo_conserva_codigo_entidad_aunque_no_exista_en_procesos(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'jbjy-vk9h')) {
                return Http::response([[
                    'id_contrato' => 'CO1.PCCNTR.10017157',
                    'proceso_de_compra' => 'CO1.BDOS.10947183',
                    'referencia_del_contrato' => '2458 DE 2026',
                    'documento_proveedor' => '1026560711',
                    'codigo_entidad' => '700817075',
                ]]);
            }
            return Http::response([]);
        });

        $result = (new DatosAbiertosSecopService(new SecopNormalizer()))
            ->consultarPorDocumento('1026560711');

        $this->assertSame('700817075', $result[0]['codigo_dependencia']);
        $this->assertNull($result[0]['nombre_dependencia']);
    }

    public function test_consulta_vigencia_agrupa_varios_documentos_en_una_peticion_por_fuente(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'jbjy-vk9h')) {
                return Http::response([
                    [
                        'id_contrato' => 'CO1.PCCNTR.1',
                        'referencia_del_contrato' => '1-2026',
                        'documento_proveedor' => '111',
                        'nit_entidad' => '900220547',
                        'fecha_de_firma' => '2026-01-10T00:00:00.000',
                        'fecha_de_inicio_del_contrato' => '2026-01-11T00:00:00.000',
                        'fecha_de_fin_del_contrato' => '2026-03-11T00:00:00.000',
                    ],
                    [
                        'id_contrato' => 'CO1.PCCNTR.2',
                        'referencia_del_contrato' => '2-2026',
                        'documento_proveedor' => '222',
                        'nit_entidad' => '900220547',
                        'fecha_de_firma' => '2026-02-10T00:00:00.000',
                        'fecha_de_inicio_del_contrato' => '2026-02-11T00:00:00.000',
                        'fecha_de_fin_del_contrato' => '2026-04-11T00:00:00.000',
                    ],
                ]);
            }
            if (str_contains($request->url(), 'f789-7hwg')) {
                return Http::response([]);
            }
            return Http::response([]);
        });

        $result = (new DatosAbiertosSecopService(new SecopNormalizer()))
            ->consultarPorDocumentosVigencia(['111', '222'], 2026, 50000, ['900220547']);

        $this->assertSame([111, 222], array_keys($result));
        $this->assertSame('CO1.PCCNTR.1', $result['111'][0]['identificador_externo']);
        $this->assertSame('CO1.PCCNTR.2', $result['222'][0]['identificador_externo']);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            if (!str_contains($request->url(), 'jbjy-vk9h')) return false;
            $url = urldecode($request->url());
            return str_contains($url, "documento_proveedor in('111','222')");
        });
    }
}
