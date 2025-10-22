<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeguimientoExport implements FromCollection, WithHeadings
{
    protected $entries;

    public function __construct($entries)
    {
        $this->entries = $entries; // Puede ser una Collection o un array
    }

    public function collection()
    {
        return collect($this->entries)->map(function ($s) {
            return [
                'cedula_o_nit'                => $s->persona->cedula_o_nit ?? null,
                'tipo'                        => $s->tipo,
                'secretaria_id'               => $s->secretaria_id,
                'gerencia_id'                 => $s->gerencia_id,
                'fuente_id'                   => $s->fuente_id,
                'estado_contrato_id'          => $s->estado_contrato_id,
                'anio'                        => $s->anio,
                'numero_contrato'             => $s->numero_contrato,
                'fecha_acta_inicio'           => $s->fecha_acta_inicio,
                'fecha_finalizacion'          => $s->fecha_finalizacion,
                'tiempo_ejecucion_dias'       => $s->tiempo_ejecucion_dias,
                'valor_mensual'               => $s->valor_mensual,
                'valor_total'                 => $s->valor_total,
                'aut_despacho'                => $s->aut_despacho ? 'SI' : 'NO',
                'aut_planeacion'              => $s->aut_planeacion ? 'SI' : 'NO',
                'aut_administrativa'          => $s->aut_administrativa ? 'SI' : 'NO',
                'fecha_aut_despacho'          => $s->fecha_aut_despacho,
                'fecha_aut_planeacion'        => $s->fecha_aut_planeacion,
                'fecha_aut_administrativa'    => $s->fecha_aut_administrativa,
                'adicion'                     => $s->adicion,
                'fecha_acta_inicio_adicion'   => $s->fecha_acta_inicio_adicion,
                'fecha_finalizacion_adicion'  => $s->fecha_finalizacion_adicion,
                'tiempo_ejecucion_dias_adicion' => $s->tiempo_ejecucion_dias_adicion,
                'tiempo_total_ejecucion_dias' => $s->tiempo_total_ejecucion_dias,
                'valor_adicion'               => $s->valor_adicion,
                'valor_total_contrato'        => $s->valor_total_contrato,
                'evaluacion_id'               => $s->evaluacion_id,
                'continua'                    => $s->continua,
                'observaciones_contrato'      => $s->observaciones_contrato,
            ];
        });
    }

    public function headings(): array
    {
        return [
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
            'fecha_aut_despacho',
            'fecha_aut_planeacion',
            'fecha_aut_administrativa',
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
    }
}
