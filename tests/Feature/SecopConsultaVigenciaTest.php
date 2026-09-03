<?php

namespace Tests\Feature;

use App\Services\DatosAbiertosSecopService;
use App\Services\SecopNormalizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecopConsultaVigenciaTest extends TestCase
{
    public function test_consulta_limita_secop_i_y_ii_y_filtra_por_superposicion(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'jbjy-vk9h')) {
                return Http::response([
                    [
                        'id_contrato' => 'CO1.PCCNTR.CARRY',
                        'referencia_del_contrato' => '100-2025',
                        'estado_contrato' => 'En ejecución',
                        'nit_entidad' => '892000148',
                        'fecha_de_firma' => '2025-12-01',
                        'fecha_de_inicio_del_contrato' => '2025-12-15',
                        'fecha_de_fin_del_contrato' => '2026-03-01',
                        'documento_proveedor' => '123',
                    ],
                    [
                        'id_contrato' => 'CO1.PCCNTR.MUNICIPIO',
                        'referencia_del_contrato' => '200-2026',
                        'estado_contrato' => 'En ejecución',
                        'nit_entidad' => '800000001',
                        'fecha_de_firma' => '2026-01-01',
                        'fecha_de_inicio_del_contrato' => '2026-01-02',
                        'fecha_de_fin_del_contrato' => '2026-06-01',
                        'documento_proveedor' => '123',
                    ],
                    [
                        'id_contrato' => 'CO1.PCCNTR.FUTURE',
                        'referencia_del_contrato' => '100-2027',
                        'estado_contrato' => 'En ejecución',
                        'fecha_de_firma' => '2027-01-01',
                        'fecha_de_inicio_del_contrato' => '2027-01-02',
                        'fecha_de_fin_del_contrato' => '2027-06-01',
                        'documento_proveedor' => '123',
                    ],
                ]);
            }
            return Http::response([]);
        });

        $result = (new DatosAbiertosSecopService(new SecopNormalizer()))
            ->consultarPorDocumentoVigencia('123', 2026, 1000, ['8920001488']);

        $this->assertSame(['CO1.PCCNTR.CARRY'], collect($result)->pluck('identificador_externo')->all());
        Http::assertSent(function (Request $request) {
            $where = (string) ($request->data()['$where'] ?? '');
            return str_contains($where, '2026-01-01')
                && str_contains($where, '2027-01-01')
                && (str_contains($where, '8920001488') || str_contains($where, '892000148'));
        });
        Http::assertSentCount(2);
    }
}
