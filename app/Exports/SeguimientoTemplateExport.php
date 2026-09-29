<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB; // Necesario para obtener los datos de los catálogos

class SeguimientoTemplateExport implements WithMultipleSheets
{
    /**
     * Define las hojas que contendrá el archivo Excel.
     * La primera es la plantilla, las siguientes son los catálogos de referencia.
     */
    public function sheets(): array
    {
        $sheets = [];

        // 1. Hoja principal: La plantilla de Seguimientos
        $sheets[] = new class implements FromCollection, WithHeadings, WithTitle {
            public function headings(): array
            {
                return SeguimientoExport::HEADINGS;
            }
            public function collection()
            {
                // Colección vacía para solo mostrar encabezados
                return Collection::make([]);
            }
            public function title(): string
            {
                return 'Plantilla_Seguimientos';
            }
        };

        // 2. Hojas de Catálogos (Referencia de IDs)
        $catalogos = [
            ['table' => 'secretarias', 'title' => 'C_Secretarias'],
            ['table' => 'gerencias', 'title' => 'C_Gerencias'],
            ['table' => 'fuentes', 'title' => 'C_Fuentes'],
            ['table' => 'estados', 'title' => 'C_Estados'], // Se usa para estado_id y estado_contrato_id
            ['table' => 'evaluaciones', 'title' => 'C_Evaluaciones'],
            ['table' => 'niveles_academicos', 'title' => 'C_Nivel_Academico'],
        ];

        foreach ($catalogos as $catalogo) {
            $sheets[] = new class($catalogo['table'], $catalogo['title']) implements FromCollection, WithHeadings, WithTitle {
                private $tableName;
                private $title;

                public function __construct(string $tableName, string $title)
                {
                    $this->tableName = $tableName;
                    $this->title = $title;
                }

                public function collection()
                {
                    // Obtiene ID y el campo que mejor sirva como nombre o descripción
                    return DB::table($this->tableName)
                            ->select('id', DB::raw('nombre as descripcion'))
                            ->get();
                }

                public function headings(): array
                {
                    return ['ID', 'NOMBRE / DESCRIPCIÓN'];
                }

                public function title(): string
                {
                    return $this->title;
                }
            };
        }

        return $sheets;
    }
}
