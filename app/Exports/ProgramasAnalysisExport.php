<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProgramasAnalysisExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters,
        private readonly array $summary,
        private readonly CarbonInterface $generatedAt,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->dataSheet(),
            $this->municipalitySheet(),
            $this->projectSheet(),
            $this->referenceSheet('Referencia 1', $this->summary['peopleByReference1']),
            $this->referenceSheet('Referencia 2', $this->summary['peopleByReference2']),
        ];
    }

    private function summarySheet(): ProgramasReportSheet
    {
        $filterLabels = [
            'municipio' => 'Municipio',
            'operador' => 'Proyecto',
            'perfil' => 'Perfil',
            'estado' => 'Estado',
            'referencia_1' => 'Referencia 1',
            'referencia_2' => 'Referencia 2',
            'buscar' => 'Búsqueda',
        ];
        $rows = collect([
            ['METADATO', 'Fecha de generación', $this->generatedAt->format('d/m/Y H:i:s')],
        ]);

        foreach ($filterLabels as $key => $label) {
            $value = $this->filters[$key] ?? '';
            $rows->push(['FILTRO', $label, $value === '' ? 'Sin filtro' : $value]);
        }

        foreach ([
            'personas' => 'Personas únicas',
            'proyectos' => 'Proyectos',
            'municipios' => 'Municipios',
            'asignaciones' => 'Asignaciones',
            'referencias_1' => 'Referencias 1',
            'referencias_2' => 'Referencias 2',
        ] as $key => $label) {
            $rows->push(['INDICADOR', $label, $this->summary['metrics'][$key]]);
        }

        return new ProgramasReportSheet('Resumen', ['CATEGORÍA', 'CONCEPTO', 'VALOR'], $rows);
    }

    private function dataSheet(): ProgramasReportSheet
    {
        $rows = $this->rows->map(fn ($row) => [
            $row->external_id ?? '',
            $row->nombre_apellidos ?? '',
            $row->cedula ?? '',
            $row->contacto ?? '',
            $row->municipio ?? '',
            $row->operador ?? '',
            $row->perfil ?? '',
            $row->institucion ?? '',
            $row->ref_1 ?? '',
            $row->ref_2 ?? '',
            $row->estado ?? '',
        ]);

        return new ProgramasReportSheet('Datos', [
            'ID INTEGRA', 'PERSONA', 'CÉDULA', 'CONTACTO', 'MUNICIPIO', 'PROYECTO',
            'PERFIL', 'INSTITUCIÓN / SEDE', 'REFERENCIA 1', 'REFERENCIA 2', 'ESTADO',
        ], $rows);
    }

    private function municipalitySheet(): ProgramasReportSheet
    {
        $rows = $this->summary['projectsByMunicipality']->map(fn (array $item) => [
            $item['nombre'],
            $item['personas'],
            $item['proyectos'],
            $item['asignaciones'],
            $item['operadores']->implode(', '),
        ]);

        return new ProgramasReportSheet('Por municipio', [
            'MUNICIPIO', 'PERSONAS ÚNICAS', 'PROYECTOS', 'ASIGNACIONES', 'DETALLE DE PROYECTOS',
        ], $rows);
    }

    private function projectSheet(): ProgramasReportSheet
    {
        $rows = $this->summary['peopleByProject']->map(fn (array $item) => [
            $item['nombre'],
            $item['personas'],
            $item['municipios']->count(),
            $item['asignaciones'],
            $item['municipios']->implode(', '),
        ]);

        return new ProgramasReportSheet('Por proyecto', [
            'PROYECTO', 'PERSONAS ÚNICAS', 'MUNICIPIOS', 'ASIGNACIONES', 'DETALLE DE MUNICIPIOS',
        ], $rows);
    }

    private function referenceSheet(string $title, Collection $breakdown): ProgramasReportSheet
    {
        $rows = $breakdown->map(fn (array $item) => [
            $item['nombre'],
            $item['personas'],
            $item['proyectos'],
            $item['asignaciones'],
            $item['operadores']->implode(', '),
        ]);

        return new ProgramasReportSheet($title, [
            strtoupper($title), 'PERSONAS ÚNICAS', 'PROYECTOS', 'ASIGNACIONES', 'DETALLE DE PROYECTOS',
        ], $rows);
    }
}
