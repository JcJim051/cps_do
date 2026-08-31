<?php

namespace Tests\Unit;

use App\Models\Persona;
use App\Models\Seguimiento;
use App\Services\DatosAbiertosSecopService;
use App\Services\SecopConciliacionService;
use App\Services\SecopEntidadService;
use App\Services\SecopNormalizer;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class SecopConciliacionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_solo_declara_exacta_la_coincidencia_de_documento_entidad_numero_y_vigencia(): void
    {
        $normalizer = new SecopNormalizer();
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->twice()->andReturn('9002205475');
        $datos = Mockery::mock(DatosAbiertosSecopService::class);
        $service = new SecopConciliacionService($datos, $normalizer, $entity);

        $persona = new Persona(['cedula_o_nit' => '1030590916']);
        $correct = $this->tracking(1, '59-2026', 2026);
        $wrongDocument = $this->tracking(2, '652-2025', 2025);
        $contracts = collect([
            $this->contract('CONTRATO 059 DE 2026', '1030590916', 'CO1.OK', '2026-01-16'),
            $this->contract('CONTRATO 652 DE 2025', '999999', 'CO1.WRONG', '2025-11-20'),
        ]);

        $result = $service->conciliar($persona, collect([$correct, $wrongDocument]), $contracts, collect());

        $this->assertSame('exacto', $result['filas'][0]['estado']);
        $this->assertTrue($result['filas'][0]['seguro']);
        $this->assertSame('requiere_revision', $result['filas'][1]['estado']);
        $this->assertFalse($result['filas'][1]['seguro']);
    }

    private function tracking(int $id, string $number, int $year): Seguimiento
    {
        $tracking = new Seguimiento([
            'tipo' => 'contrato',
            'numero_contrato' => $number,
            'anio' => $year,
            'valor_total' => 100,
        ]);
        $tracking->id = $id;
        $tracking->setRelation('vinculoSecop', null);

        return $tracking;
    }

    private function contract(string $reference, string $document, string $externalId, string $signedAt): array
    {
        return [
            'tipo_registro' => 'contrato',
            'fuente_codigo' => 'secop2',
            'identificador_externo' => $externalId,
            'referencia_contrato' => $reference,
            'documento' => $document,
            'nit_entidad' => '900220547',
            'fecha_firma' => $signedAt,
            'valor_total_con_adiciones' => 100,
        ];
    }
}
