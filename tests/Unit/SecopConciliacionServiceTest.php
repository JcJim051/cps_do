<?php

namespace Tests\Unit;

use App\Models\Persona;
use App\Models\Seguimiento;
use App\Models\Secretaria;
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
        $this->assertSame('sin_resultado', $result['filas'][1]['estado']);
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

    public function test_un_mismo_contrato_no_se_declara_exacto_para_dos_seguimientos(): void
    {
        $normalizer = new SecopNormalizer();
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->twice()->andReturn('9002205475');
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            $normalizer,
            $entity,
        );

        $persona = new Persona(['cedula_o_nit' => '1030590916']);
        $first = $this->tracking(1, '59-2026', 2026);
        $duplicate = $this->tracking(2, 'CONTRATO 059 DE 2026', 2026);
        $contracts = collect([
            $this->contract('CONTRATO 059 DE 2026', '1030590916', 'CO1.SAME', '2026-01-16'),
        ]);

        $result = $service->conciliar($persona, collect([$first, $duplicate]), $contracts, collect());

        $this->assertSame('exacto', $result['filas'][0]['estado']);
        $this->assertSame('conflicto', $result['filas'][1]['estado']);
        $this->assertFalse($result['filas'][1]['seguro']);
        $this->assertSame(1, $result['metricas']['exactos']);
    }

    public function test_vincula_por_fechas_y_valor_cuando_el_numero_local_esta_vacio(): void
    {
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->once()->andReturn('8920001488');
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            new SecopNormalizer(),
            $entity,
        );
        $persona = new Persona(['cedula_o_nit' => '1030590916']);
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'tipo' => 'contrato',
            'anio' => 2026,
            'numero_contrato' => null,
            'aut_despacho' => true,
            'aut_planeacion' => true,
            'fecha_acta_inicio' => '2026-01-01',
            'fecha_finalizacion' => '2026-06-30',
            'tiempo_ejecucion_dias' => 180,
            'valor_mensual' => 5000000,
            'valor_total' => 30000000,
            'valor_total_contrato' => 30000000,
        ]);
        $tracking->id = 10;
        $tracking->setRelation('vinculoSecop', null);
        $contracts = collect([array_merge(
            $this->contract('CONTRATO 999 DE 2026', '1030590916', 'CO1.APPROX', '2026-01-05'),
            [
                'nit_entidad' => '892000148',
                'fecha_inicio' => '2026-01-11',
                'fecha_fin' => '2026-06-20',
                'valor_contrato' => 28333333,
                'valor_total_con_adiciones' => 28333333,
            ],
        )]);

        $result = $service->conciliar($persona, collect([$tracking]), $contracts, collect());

        $this->assertSame('aproximado', $result['filas'][0]['estado']);
        $this->assertTrue($result['filas'][0]['seguro']);
        $this->assertGreaterThanOrEqual(75, $result['filas'][0]['puntaje']);
        $this->assertSame(1, $result['metricas']['aproximados']);
        $this->assertSame(1, $result['metricas']['automaticos']);
    }

    public function test_codigo_pci_distingue_la_secretaria_central_antes_de_declarar_ambiguedad(): void
    {
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->once()->andReturn('8920001488');
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            new SecopNormalizer(),
            $entity,
        );
        $persona = new Persona(['cedula_o_nit' => '123']);
        $tracking = $this->tracking(20, '59-2026', 2026);
        $secretaria = new Secretaria();
        $secretaria->setRawAttributes(['codigo_pci_secop' => 'PCI-SALUD']);
        $tracking->setRelation('secretaria', $secretaria);
        $base = array_replace(
            $this->contract('59-2026', '123', 'CO1.MATCH', '2026-01-10'),
            ['nit_entidad' => '892000148'],
        );
        $contracts = collect([
            $base + ['codigo_dependencia' => 'PCI-SALUD'],
            array_replace($base, ['identificador_externo' => 'CO1.OTHER', 'codigo_dependencia' => 'PCI-HACIENDA']),
        ]);

        $result = $service->conciliar($persona, collect([$tracking]), $contracts, collect());

        $this->assertSame('exacto', $result['filas'][0]['estado']);
        $this->assertSame('CO1.MATCH', $result['filas'][0]['candidato']['identificador_externo']);
    }

    public function test_vincula_por_plazo_publicado_y_valor_cuando_integra_no_tiene_fechas(): void
    {
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->once()->andReturn('900220547');
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            new SecopNormalizer(),
            $entity,
        );
        $persona = new Persona(['cedula_o_nit' => '40217161']);
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'tipo' => 'contrato',
            'anio' => 2026,
            'numero_contrato' => null,
            'aut_despacho' => true,
            'aut_planeacion' => true,
            'tiempo_ejecucion_dias' => 73,
            'valor_mensual' => 4910000,
            'valor_total' => 11947667,
            'valor_total_contrato' => 11947667,
        ]);
        $tracking->id = 17171;
        $tracking->setRelation('vinculoSecop', null);

        $candidate = array_replace(
            $this->contract('CONTRATO 0556 DE 2026', '40217161', 'CO1.PCCNTR.10081281', '2026-10-16'),
            [
                'nit_entidad' => '900220547',
                'fecha_inicio' => '2026-10-19',
                'fecha_fin' => '2026-12-20',
                'duracion_inicial' => '73 Dia(s)',
                'valor_contrato' => 11947618,
                'valor_total_con_adiciones' => 11947618,
            ],
        );

        $result = $service->conciliar($persona, collect([$tracking]), collect([$candidate]), collect());
        $row = $result['filas'][0];

        $this->assertSame('aproximado', $row['estado']);
        $this->assertTrue($row['seguro']);
        $this->assertGreaterThanOrEqual(75, $row['puntaje']);
        $this->assertContains('Plazo publicado exacto: 73 días', $row['razones']);
        $this->assertSame('CO1.PCCNTR.10081281', $row['candidato']['identificador_externo']);
    }

    public function test_no_vincula_aproximadamente_si_dos_candidatos_tienen_el_mismo_puntaje(): void
    {
        $entity = Mockery::mock(SecopEntidadService::class);
        $entity->shouldReceive('nitParaSeguimiento')->once()->andReturn('8920001488');
        $service = new SecopConciliacionService(
            Mockery::mock(DatosAbiertosSecopService::class),
            new SecopNormalizer(),
            $entity,
        );
        $persona = new Persona(['cedula_o_nit' => '123']);
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'tipo' => 'contrato', 'anio' => 2026, 'numero_contrato' => null,
            'aut_despacho' => true, 'aut_planeacion' => true,
            'fecha_acta_inicio' => '2026-01-01', 'fecha_finalizacion' => '2026-06-30',
            'tiempo_ejecucion_dias' => 180, 'valor_total' => 30000000,
        ]);
        $tracking->id = 30;
        $tracking->setRelation('vinculoSecop', null);
        $base = [
            'tipo_registro' => 'contrato', 'fuente_codigo' => 'secop2',
            'referencia_contrato' => 'SIN NUMERO', 'documento' => '123', 'nit_entidad' => '892000148',
            'fecha_firma' => '2026-01-01', 'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-06-25',
            'valor_contrato' => 29000000, 'valor_total_con_adiciones' => 29000000,
        ];
        $contracts = collect([
            $base + ['identificador_externo' => 'CO1.A'],
            $base + ['identificador_externo' => 'CO1.B'],
        ]);

        $result = $service->conciliar($persona, collect([$tracking]), $contracts, collect());

        $this->assertSame('requiere_revision', $result['filas'][0]['estado']);
        $this->assertFalse($result['filas'][0]['seguro']);
        $this->assertSame(0, $result['filas'][0]['margen']);
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

    public function test_tiempo_vigente_ignora_totales_derivados_en_cero_si_existe_plazo_inicial(): void
    {
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'tiempo_ejecucion_dias' => 92,
            'tiempo_total_ejecucion_dias' => 0,
            'tiempo_total_calendario_dias' => 0,
        ]);

        $this->assertSame(92, $tracking->tiempo_total_vigente_dias);

        $tracking->setRawAttributes([
            'tiempo_ejecucion_dias' => 0,
            'tiempo_total_ejecucion_dias' => 0,
            'tiempo_total_calendario_dias' => 0,
        ]);

        $this->assertSame(0, $tracking->tiempo_total_vigente_dias);
    }

    public function test_tiempo_vigente_sin_modificacion_prioriza_el_plazo_inicial_actualizado(): void
    {
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'adicion' => 'NO',
            'tiempo_ejecucion_dias' => 153,
            'tiempo_total_ejecucion_dias' => 165,
            'tiempo_total_calendario_dias' => null,
        ]);

        $this->assertSame(153, $tracking->tiempo_total_vigente_dias);
    }

    public function test_tiempo_vigente_con_modificacion_se_construye_desde_sus_componentes(): void
    {
        $tracking = new Seguimiento();
        $tracking->setRawAttributes([
            'adicion' => 'SI',
            'tiempo_ejecucion_dias' => 135,
            'tiempo_ejecucion_dias_adicion' => 67,
            'tiempo_extension_secop_dias' => 81,
            'tiempo_suspension_dias' => 14,
            'tiempo_total_ejecucion_dias' => 202,
            'tiempo_total_calendario_dias' => 200,
        ]);

        $this->assertSame(216, $tracking->tiempo_total_vigente_dias);
        $this->assertSame(202, $tracking->tiempo_total_ejecucion_vigente_dias);
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
