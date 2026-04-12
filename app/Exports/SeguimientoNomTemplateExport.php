<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeguimientoNomTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new class implements FromCollection, WithHeadings, WithTitle {
            public function collection()
            {
                return new Collection();
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

            public function title(): string
            {
                return 'Plantilla_Seguimientos_Nom';
            }
        };

        $catalogos = [
            ['table' => 'secretarias', 'title' => 'C_Dependencias'],
            ['table' => 'cargos', 'title' => 'C_Cargos'],
            ['table' => 'tipos_vinculacion', 'title' => 'C_Tipos_Vinculacion'],
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
                    $columns = DB::getSchemaBuilder()->getColumnListing($this->tableName);
                    $nameColumn = collect($columns)->first(function ($col) {
                        return in_array($col, ['nombre', 'descripcion', 'detalle', 'titulo', 'valor', 'name']);
                    }) ?? $columns[1] ?? 'id';

                    return DB::table($this->tableName)
                        ->select('id', DB::raw("$nameColumn as descripcion"))
                        ->orderBy('id')
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
