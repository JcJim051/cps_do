<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgramaTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'operador',
            'municipio',
            'institucion',
            'sede',
            'nombre_apellidos',
            'cedula',
            'contacto',
            'ref_1',
            'ref_2',
        ];
    }

    public function collection()
    {
        return Collection::make([]);
    }
}

