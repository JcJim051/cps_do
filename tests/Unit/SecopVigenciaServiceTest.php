<?php

namespace Tests\Unit;

use App\Models\SecopInstantanea;
use App\Models\SecopVinculo;
use App\Models\Seguimiento;
use App\Services\SecopVigenciaService;
use PHPUnit\Framework\TestCase;

class SecopVigenciaServiceTest extends TestCase
{
    public function test_incluye_contratos_que_se_cruzan_con_2026_o_firmados_sin_fechas(): void
    {
        $service = new SecopVigenciaService();

        $this->assertTrue($service->contratoPertenece([
            'fecha_firma' => '2025-11-01', 'fecha_inicio' => '2025-12-15', 'fecha_fin' => '2026-02-15',
        ], 2026));
        $this->assertFalse($service->contratoPertenece([
            'fecha_firma' => '2025-01-01', 'fecha_inicio' => '2025-01-10', 'fecha_fin' => '2025-12-31',
        ], 2026));
        $this->assertTrue($service->contratoPertenece(['fecha_firma' => '2026-04-20'], 2026));
        $this->assertFalse($service->contratoPertenece([
            'fecha_firma' => '2027-01-01', 'fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-06-10',
        ], 2026));
    }

    public function test_incluye_seguimientos_por_anio_fechas_locales_o_instantanea(): void
    {
        $service = new SecopVigenciaService();

        $byYear = $this->tracking(['anio' => 2026]);
        $byLocalDates = $this->tracking([
            'anio' => 2025, 'fecha_acta_inicio' => '2025-12-01', 'fecha_finalizacion' => '2026-01-31',
        ]);
        $bySnapshot = $this->tracking(['anio' => 2025]);
        $link = new SecopVinculo();
        $snapshot = new SecopInstantanea();
        $snapshot->setRawAttributes(['fecha_inicio' => '2025-10-01', 'fecha_fin' => '2026-03-01']);
        $link->setRelation('ultimaInstantanea', $snapshot);
        $bySnapshot->setRelation('vinculoSecop', $link);
        $outside = $this->tracking([
            'anio' => 2025, 'fecha_acta_inicio' => '2025-01-01', 'fecha_finalizacion' => '2025-12-31',
        ]);

        $this->assertTrue($service->seguimientoPertenece($byYear, 2026));
        $this->assertTrue($service->seguimientoPertenece($byLocalDates, 2026));
        $this->assertTrue($service->seguimientoPertenece($bySnapshot, 2026));
        $this->assertFalse($service->seguimientoPertenece($outside, 2026));
    }

    public function test_separa_estados_contractuales_de_alertas_secop(): void
    {
        $service = new SecopVigenciaService();

        foreach (['En ejecución', 'Suspendido', 'Modificado', 'Terminado sin Liquidar', 'Liquidado'] as $state) {
            $this->assertSame('solo_secop', $service->tipoHallazgoContrato($state));
        }
        foreach (['Borrador', 'Cancelado', 'En aprobación', 'Estado nuevo', null] as $state) {
            $this->assertSame('alerta_secop', $service->tipoHallazgoContrato($state));
        }
    }

    private function tracking(array $attributes): Seguimiento
    {
        $tracking = new Seguimiento();
        $tracking->setRawAttributes($attributes);
        $tracking->setRelation('vinculoSecop', null);
        return $tracking;
    }
}
