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
}
