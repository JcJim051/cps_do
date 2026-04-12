<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeguimientoNomExport implements FromCollection, WithHeadings
{
    protected $entries;

    public function __construct($entries)
    {
        $this->entries = $entries;
    }

    public function collection()
    {
        return collect($this->entries)->map(function ($s) {
            return [
                'id' => $s->id ?? null,
                'cedula_o_nit' => $s->persona->cedula_o_nit ?? null,
                'dependencia_id' => $s->secretaria_id,
                'cargo_id' => $s->cargo_id,
                'tipo_vinculacion_id' => $s->tipo_vinculacion_id,
                'fecha_ingreso' => $s->fecha_ingreso,
                'salario' => $s->salario,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'id',
            'cedula_o_nit',
            'dependencia_id',
            'cargo_id',
            'tipo_vinculacion_id',
            'fecha_ingreso',
            'salario',
        ];
    }
}
