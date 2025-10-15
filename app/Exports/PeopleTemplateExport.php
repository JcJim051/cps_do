<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PeopleTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [];

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Hoja principal: Plantilla Personas
        |--------------------------------------------------------------------------
        */
        $sheets[] = new class implements FromCollection, WithHeadings, WithTitle {
            public function collection()
            {
                return new Collection(); // Hoja vacía, solo encabezados
            }

            public function headings(): array
            {
                return [
                    'nombre_contratista',
                    'cedula_o_nit',
                    'celular',
                    'genero',
                    'estado_persona_id',
                    'tipos_id',
                    'nivel_academico_id',
                    'tecnico_tecnologo_profesion',
                    'especializacion',
                    'maestria',
                    'caso_id',
                    'secretaria_id',
                    'gerencia_id',
                    'no_tocar',
                    'referencias_id', // IDs separados por coma
                    'referencias_2',
                ];
            }

            public function title(): string
            {
                return 'Plantilla_Personas';
            }
        };

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Hojas de catálogos de referencia
        |--------------------------------------------------------------------------
        */
        $catalogos = [
            ['table' => 'referencias', 'title' => 'C_referencias'],
            ['table' => 'estado_personas', 'title' => 'C_Estados_Persona'],
            ['table' => 'tipos', 'title' => 'C_Tipos'],
            ['table' => 'niveles_academicos', 'title' => 'C_Nivel_Academico'],
            ['table' => 'casos', 'title' => 'C_Casos'],
            ['table' => 'secretarias', 'title' => 'C_Secretarias'],
            ['table' => 'gerencias', 'title' => 'C_Gerencias'],
        ];

        foreach ($catalogos as $catalogo) {
            $sheets[] = new class($catalogo['table'], $catalogo['title']) implements FromCollection, WithHeadings, WithTitle {
                private $table;
                private $title;

                public function __construct(string $table, string $title)
                {
                    $this->table = $table;
                    $this->title = $title;
                }

                public function collection()
                {
                    // Detecta automáticamente la columna de nombre o descripción
                    $columns = DB::getSchemaBuilder()->getColumnListing($this->table);
                    $nameColumn = collect($columns)->first(function ($col) {
                        return in_array($col, ['nombre', 'descripcion', 'detalle', 'titulo', 'valor', 'name']);
                    }) ?? $columns[1] ?? 'id';

                    return DB::table($this->table)
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
