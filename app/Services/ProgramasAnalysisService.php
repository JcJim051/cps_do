<?php

namespace App\Services;

use App\Models\Programa;
use App\Support\ProgramaMunicipios;
use Illuminate\Support\Collection;

class ProgramasAnalysisService
{
    public function resolve(array $input): array
    {
        $all = Programa::query()
            ->where('origen', 'google_sheets')
            ->where('presente_en_origen', true)
            ->orderBy('nombre_apellidos')
            ->get();

        $filters = [
            'municipio' => trim((string) ($input['municipio'] ?? '')),
            'operador' => trim((string) ($input['operador'] ?? '')),
            'perfil' => trim((string) ($input['perfil'] ?? '')),
            'estado' => trim((string) ($input['estado'] ?? 'VIGENTE')),
            'referencia_1' => trim((string) ($input['referencia_1'] ?? '')),
            'referencia_2' => trim((string) ($input['referencia_2'] ?? '')),
            'buscar' => trim((string) ($input['buscar'] ?? '')),
        ];

        $municipalityRows = $all->when(
            $filters['municipio'] !== '',
            fn (Collection $rows) => $rows->where('municipio', $filters['municipio'])->values()
        );
        $dependentOptions = [
            'operadores' => $municipalityRows->pluck('operador')->filter()->unique()->sort()->values(),
            'perfiles' => $municipalityRows->pluck('perfil')->filter()->unique()->sort()->values(),
            'estados' => $municipalityRows->pluck('estado')->filter()->unique()->sort()->values(),
            'referencias_1' => $municipalityRows->pluck('ref_1')->filter()->unique()->sort()->values(),
            'referencias_2' => $municipalityRows->pluck('ref_2')->filter()->unique()->sort()->values(),
        ];

        if ($filters['operador'] !== '' && ! $dependentOptions['operadores']->containsStrict($filters['operador'])) {
            $filters['operador'] = '';
        }
        if ($filters['perfil'] !== '' && ! $dependentOptions['perfiles']->containsStrict($filters['perfil'])) {
            $filters['perfil'] = '';
        }
        if ($filters['estado'] !== '' && $filters['estado'] !== 'TODOS'
            && ! $dependentOptions['estados']->containsStrict($filters['estado'])) {
            $filters['estado'] = $dependentOptions['estados']->containsStrict('VIGENTE') ? 'VIGENTE' : 'TODOS';
        }
        if ($filters['referencia_1'] !== '' && ! $dependentOptions['referencias_1']->containsStrict($filters['referencia_1'])) {
            $filters['referencia_1'] = '';
        }
        if ($filters['referencia_2'] !== '' && ! $dependentOptions['referencias_2']->containsStrict($filters['referencia_2'])) {
            $filters['referencia_2'] = '';
        }

        $analysisRows = $all->filter(function (Programa $programa) use ($filters) {
            if ($filters['municipio'] !== '' && $programa->municipio !== $filters['municipio']) return false;
            if ($filters['operador'] !== '' && $programa->operador !== $filters['operador']) return false;
            if ($filters['perfil'] !== '' && $programa->perfil !== $filters['perfil']) return false;
            if ($filters['referencia_1'] !== '' && $programa->ref_1 !== $filters['referencia_1']) return false;
            if ($filters['referencia_2'] !== '' && $programa->ref_2 !== $filters['referencia_2']) return false;
            if ($filters['estado'] !== '' && $filters['estado'] !== 'TODOS'
                && ProgramaMunicipios::normalize($programa->estado) !== ProgramaMunicipios::normalize($filters['estado'])) return false;

            return true;
        })->values();

        $detailRows = $this->search($analysisRows, $filters['buscar']);

        return [
            'filters' => $filters,
            'options' => [
                'municipios' => $all->pluck('municipio')->filter()->unique()->sort()->values(),
                ...$dependentOptions,
            ],
            'analysisRows' => $analysisRows,
            'detailRows' => $detailRows,
        ];
    }

    public function summarize(Collection $rows): array
    {
        $peopleByProject = $rows->groupBy('operador')->map(function (Collection $projectRows, string $operator) {
            return [
                'nombre' => $operator,
                'personas' => $projectRows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'asignaciones' => $projectRows->count(),
                'municipios' => $projectRows->pluck('municipio')->filter()->unique()->sort()->values(),
            ];
        })->sortByDesc('personas')->values();

        $projectsByMunicipality = $rows->groupBy('municipio')->map(function (Collection $municipalityRows, string $municipality) {
            return [
                'nombre' => $municipality,
                'proyectos' => $municipalityRows->pluck('operador')->filter()->unique()->count(),
                'personas' => $municipalityRows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'asignaciones' => $municipalityRows->count(),
                'operadores' => $municipalityRows->pluck('operador')->filter()->unique()->sort()->values(),
            ];
        })->sortByDesc('proyectos')->values();

        $peopleDetails = $rows->groupBy(fn (Programa $row) => $this->personKey($row))
            ->map(function (Collection $personRows) {
                $person = $personRows->first();

                return [
                    'nombre' => $person->nombre_apellidos,
                    'cedula' => $person->cedula,
                    'proyectos' => $personRows->pluck('operador')->filter()->unique()->sort()->values(),
                    'municipios' => $personRows->pluck('municipio')->filter()->unique()->sort()->values(),
                    'asignaciones' => $personRows->count(),
                ];
            })->sortBy('nombre')->values();

        $assignmentDetails = $rows->groupBy(fn (Programa $row) => $row->operador.'|'.$row->municipio)
            ->map(function (Collection $assignmentRows) {
                $assignment = $assignmentRows->first();

                return [
                    'proyecto' => $assignment->operador,
                    'municipio' => $assignment->municipio,
                    'personas' => $assignmentRows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                    'asignaciones' => $assignmentRows->count(),
                ];
            })->sortBy([['proyecto', 'asc'], ['municipio', 'asc']])->values();

        return [
            'metrics' => [
                'personas' => $rows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'proyectos' => $rows->pluck('operador')->filter()->unique()->count(),
                'municipios' => $rows->pluck('municipio')->filter()->unique()->count(),
                'asignaciones' => $rows->count(),
                'referencias_1' => $rows->pluck('ref_1')->filter()->unique()->count(),
                'referencias_2' => $rows->pluck('ref_2')->filter()->unique()->count(),
            ],
            'peopleByProject' => $peopleByProject,
            'projectsByMunicipality' => $projectsByMunicipality,
            'peopleByReference1' => $this->referenceBreakdown($rows, 'ref_1'),
            'peopleByReference2' => $this->referenceBreakdown($rows, 'ref_2'),
            'peopleDetails' => $peopleDetails,
            'assignmentDetails' => $assignmentDetails,
        ];
    }

    public function segment(Collection $rows, string $dimension, string $value): Collection
    {
        $field = [
            'operador' => 'operador',
            'municipio' => 'municipio',
            'referencia_1' => 'ref_1',
            'referencia_2' => 'ref_2',
        ][$dimension] ?? null;

        if ($field === null) {
            return collect();
        }

        return $rows->filter(
            fn (Programa $programa) => trim((string) $programa->{$field}) === trim($value)
        )->values();
    }

    public function search(Collection $rows, ?string $term): Collection
    {
        $needle = ProgramaMunicipios::normalize(trim((string) $term));
        if ($needle === '') {
            return $rows->values();
        }

        return $rows->filter(function (Programa $programa) use ($needle) {
            $haystack = ProgramaMunicipios::normalize(implode(' ', [
                $programa->nombre_apellidos,
                $programa->cedula,
                $programa->municipio,
                $programa->operador,
                $programa->perfil,
                $programa->institucion,
                $programa->ref_1,
                $programa->ref_2,
                $programa->estado,
            ]));

            return str_contains($haystack, $needle);
        })->values();
    }

    private function personKey(Programa $programa): string
    {
        $document = trim((string) $programa->cedula);
        if ($document !== '' && ! in_array(ProgramaMunicipios::normalize($document), ['NA', 'N/A'], true)) {
            return 'DOC:'.$document;
        }

        return 'NOMBRE:'.ProgramaMunicipios::normalize($programa->nombre_apellidos);
    }

    private function referenceBreakdown(Collection $rows, string $field): Collection
    {
        return $rows->filter(fn (Programa $row) => filled($row->{$field}))
            ->groupBy(fn (Programa $row) => trim((string) $row->{$field}))
            ->map(function (Collection $referenceRows, string $reference) {
                return [
                    'nombre' => $reference,
                    'personas' => $referenceRows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                    'proyectos' => $referenceRows->pluck('operador')->filter()->unique()->count(),
                    'asignaciones' => $referenceRows->count(),
                    'operadores' => $referenceRows->pluck('operador')->filter()->unique()->sort()->values(),
                ];
            })->sortByDesc('personas')->values();
    }
}
