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

    public function test_solo_secop_excluye_contratos_que_ya_tienen_numero_y_vigencia_local(): void
    {
        $normalizer = new SecopNormalizer();
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->once()->andReturn(null);
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            $normalizer,
            $entity,
        );

        $persona = new Persona(['cedula_o_nit' => '1030590916']);
        $local = $this->tracking(1, '59-2026', 2026);
        $contracts = collect([
            $this->contract('CONTRATO 059 DE 2026', '1030590916', 'CO1.LOCAL', '2026-01-16'),
            $this->contract('CONTRATO 060 DE 2026', '1030590916', 'CO1.ONLY', '2026-02-01'),
        ]);

        $result = $service->conciliar($persona, collect([$local]), $contracts, collect());

        $this->assertSame(['CO1.ONLY'], $result['contratos_sin_seguimiento']->pluck('identificador_externo')->all());
        $this->assertSame(1, $result['metricas']['solo_secop']);
    }

    public function test_seguimiento_expone_fin_y_tiempo_calendario_vigentes_sin_perder_los_iniciales(): void
    {
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'fecha_finalizacion' => '2026-06-04',
            'fecha_finalizacion_adicion' => '2026-08-24',
            'tiempo_ejecucion_dias' => 135,
            'tiempo_total_ejecucion_dias' => 202,
            'tiempo_total_calendario_dias' => 216,
        ]);

        $this->assertSame('2026-08-24', $tracking->fecha_finalizacion_vigente->format('Y-m-d'));
        $this->assertSame(216, $tracking->tiempo_total_vigente_dias);
        $this->assertSame('2026-06-04', $tracking->fecha_finalizacion->format('Y-m-d'));
        $this->assertSame(202, $tracking->tiempo_total_ejecucion_dias);
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
