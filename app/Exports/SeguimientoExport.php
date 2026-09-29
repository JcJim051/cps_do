<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeguimientoExport implements FromCollection, WithHeadings
{
    public const HEADINGS = [
        'id',
        'cedula_o_nit',
        'tipo',
        'secretaria_id',
        'gerencia_id',
        'fuente_id',
        'estado_contrato_id',
        'anio',
        'numero_contrato',
        'fecha_acta_inicio',
        'fecha_finalizacion',
        'tiempo_ejecucion_dias',
        'valor_mensual',
        'valor_total',
        'aut_despacho',
        'aut_planeacion',
        'aut_administrativa',
        'aut_despacho_adicion',
        'aut_planeacion_adicion',
        'aut_administrativa_adicion',
        'fecha_aut_despacho',
        'fecha_aut_planeacion',
        'fecha_aut_administrativa',
        'fecha_aut_despacho_adicion',
        'fecha_aut_planeacion_adicion',
        'fecha_aut_administrativa_adicion',
        'adicion',
        'fecha_acta_inicio_adicion',
        'fecha_finalizacion_adicion',
        'tiempo_ejecucion_dias_adicion',
        'tiempo_total_ejecucion_dias',
        'valor_adicion',
        'valor_total_contrato',
        'evaluacion_id',
        'continua',
        'observaciones_contrato',
    ];

    public function __construct(protected Collection $entries)
    {
    }

    public function collection(): Collection
    {
        return $this->entries->map(function ($seguimiento) {
            return [
                'id' => $seguimiento->id,
                'cedula_o_nit' => $seguimiento->persona?->cedula_o_nit,
                'tipo' => $seguimiento->tipo,
                'secretaria_id' => $seguimiento->secretaria_id,
                'gerencia_id' => $seguimiento->gerencia_id,
                'fuente_id' => $seguimiento->fuente_id,
                'estado_contrato_id' => $seguimiento->estado_contrato_id,
                'anio' => $seguimiento->anio,
                'numero_contrato' => $seguimiento->numero_contrato,
                'fecha_acta_inicio' => $seguimiento->fecha_acta_inicio,
                'fecha_finalizacion' => $seguimiento->fecha_finalizacion,
                'tiempo_ejecucion_dias' => $seguimiento->tiempo_ejecucion_dias,
                'valor_mensual' => $seguimiento->valor_mensual,
                'valor_total' => $seguimiento->valor_total,
                'aut_despacho' => $seguimiento->aut_despacho ? 'SI' : 'NO',
                'aut_planeacion' => $seguimiento->aut_planeacion ? 'SI' : 'NO',
                'aut_administrativa' => $seguimiento->aut_administrativa ? 'SI' : 'NO',
                'aut_despacho_adicion' => $seguimiento->aut_despacho_adicion ? 'SI' : 'NO',
                'aut_planeacion_adicion' => $seguimiento->aut_planeacion_adicion ? 'SI' : 'NO',
                'aut_administrativa_adicion' => $seguimiento->aut_administrativa_adicion ? 'SI' : 'NO',
                'fecha_aut_despacho' => $seguimiento->fecha_aut_despacho,
                'fecha_aut_planeacion' => $seguimiento->fecha_aut_planeacion,
                'fecha_aut_administrativa' => $seguimiento->fecha_aut_administrativa,
                'fecha_aut_despacho_adicion' => $seguimiento->fecha_aut_despacho_adicion,
                'fecha_aut_planeacion_adicion' => $seguimiento->fecha_aut_planeacion_adicion,
                'fecha_aut_administrativa_adicion' => $seguimiento->fecha_aut_administrativa_adicion,
                'adicion' => $seguimiento->adicion,
                'fecha_acta_inicio_adicion' => $seguimiento->fecha_acta_inicio_adicion,
                'fecha_finalizacion_adicion' => $seguimiento->fecha_finalizacion_adicion,
                'tiempo_ejecucion_dias_adicion' => $seguimiento->tiempo_ejecucion_dias_adicion,
                'tiempo_total_ejecucion_dias' => $seguimiento->tiempo_total_ejecucion_dias,
                'valor_adicion' => $seguimiento->valor_adicion,
                'valor_total_contrato' => $seguimiento->valor_total_contrato,
                'evaluacion_id' => $seguimiento->evaluacion_id,
                'continua' => $seguimiento->continua,
                'observaciones_contrato' => $seguimiento->observaciones_contrato,
            ];
        });
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }
}
