<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Models\ProgramaFuente;
use App\Services\ProgramasGoogleSyncService;
use App\Support\ProgramaMunicipios;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProgramasDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeModule();

        $all = Programa::query()
            ->where('origen', 'google_sheets')
            ->where('presente_en_origen', true)
            ->orderBy('nombre_apellidos')
            ->get();

        $filters = [
            'municipio' => trim((string) $request->query('municipio', '')),
            'operador' => trim((string) $request->query('operador', '')),
            'perfil' => trim((string) $request->query('perfil', '')),
            'estado' => trim((string) $request->query('estado', 'VIGENTE')),
            'referencia_1' => trim((string) $request->query('referencia_1', '')),
            'referencia_2' => trim((string) $request->query('referencia_2', '')),
            'buscar' => trim((string) $request->query('buscar', '')),
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

        if ($filters['operador'] !== '' && !$dependentOptions['operadores']->containsStrict($filters['operador'])) {
            $filters['operador'] = '';
        }
        if ($filters['perfil'] !== '' && !$dependentOptions['perfiles']->containsStrict($filters['perfil'])) {
            $filters['perfil'] = '';
        }
        if ($filters['estado'] !== '' && $filters['estado'] !== 'TODOS'
            && !$dependentOptions['estados']->containsStrict($filters['estado'])) {
            $filters['estado'] = $dependentOptions['estados']->containsStrict('VIGENTE') ? 'VIGENTE' : 'TODOS';
        }
        if ($filters['referencia_1'] !== '' && !$dependentOptions['referencias_1']->containsStrict($filters['referencia_1'])) {
            $filters['referencia_1'] = '';
        }
        if ($filters['referencia_2'] !== '' && !$dependentOptions['referencias_2']->containsStrict($filters['referencia_2'])) {
            $filters['referencia_2'] = '';
        }

        $filtered = $all->filter(function (Programa $programa) use ($filters) {
            if ($filters['municipio'] !== '' && $programa->municipio !== $filters['municipio']) return false;
            if ($filters['operador'] !== '' && $programa->operador !== $filters['operador']) return false;
            if ($filters['perfil'] !== '' && $programa->perfil !== $filters['perfil']) return false;
            if ($filters['referencia_1'] !== '' && $programa->ref_1 !== $filters['referencia_1']) return false;
            if ($filters['referencia_2'] !== '' && $programa->ref_2 !== $filters['referencia_2']) return false;
            if ($filters['estado'] !== '' && $filters['estado'] !== 'TODOS'
                && ProgramaMunicipios::normalize($programa->estado) !== ProgramaMunicipios::normalize($filters['estado'])) return false;

            return true;
        })->values();

        $detailsFiltered = $filtered->when($filters['buscar'] !== '', function (Collection $rows) use ($filters) {
            $needle = ProgramaMunicipios::normalize($filters['buscar']);

            return $rows->filter(function (Programa $programa) use ($needle) {
                $haystack = ProgramaMunicipios::normalize(implode(' ', [
                    $programa->nombre_apellidos, $programa->cedula, $programa->operador,
                    $programa->institucion, $programa->municipio, $programa->ref_1, $programa->ref_2,
                ]));

                return str_contains($haystack, $needle);
            })->values();
        });

        $peopleByProject = $filtered->groupBy('operador')->map(function (Collection $rows, string $operator) {
            return [
                'nombre' => $operator,
                'personas' => $rows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'asignaciones' => $rows->count(),
                'municipios' => $rows->pluck('municipio')->filter()->unique()->sort()->values(),
            ];
        })->sortByDesc('personas')->values();

        $projectsByMunicipality = $filtered->groupBy('municipio')->map(function (Collection $rows, string $municipality) {
            return [
                'nombre' => $municipality,
                'proyectos' => $rows->pluck('operador')->filter()->unique()->count(),
                'personas' => $rows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'asignaciones' => $rows->count(),
                'operadores' => $rows->pluck('operador')->filter()->unique()->sort()->values(),
            ];
        })->sortByDesc('proyectos')->values();

        $peopleByReference1 = $this->referenceBreakdown($filtered, 'ref_1');
        $peopleByReference2 = $this->referenceBreakdown($filtered, 'ref_2');

        $peopleDetails = $filtered->groupBy(fn (Programa $row) => $this->personKey($row))
            ->map(function (Collection $rows) {
                $person = $rows->first();

                return [
                    'nombre' => $person->nombre_apellidos,
                    'cedula' => $person->cedula,
                    'proyectos' => $rows->pluck('operador')->filter()->unique()->sort()->values(),
                    'municipios' => $rows->pluck('municipio')->filter()->unique()->sort()->values(),
                    'asignaciones' => $rows->count(),
                ];
            })->sortBy('nombre')->values();

        $assignmentDetails = $filtered->groupBy(fn (Programa $row) => $row->operador.'|'.$row->municipio)
            ->map(function (Collection $rows) {
                $assignment = $rows->first();

                return [
                    'proyecto' => $assignment->operador,
                    'municipio' => $assignment->municipio,
                    'personas' => $rows->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                    'asignaciones' => $rows->count(),
                ];
            })->sortBy([['proyecto', 'asc'], ['municipio', 'asc']])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $details = new LengthAwarePaginator(
            $detailsFiltered->forPage($page, $perPage)->values(),
            $detailsFiltered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.programas.dashboard', [
            'title' => 'Programas',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Programas' => false,
            ],
            'filters' => $filters,
            'options' => [
                'municipios' => $all->pluck('municipio')->filter()->unique()->sort()->values(),
                'operadores' => $dependentOptions['operadores'],
                'perfiles' => $dependentOptions['perfiles'],
                'estados' => $dependentOptions['estados'],
                'referencias_1' => $dependentOptions['referencias_1'],
                'referencias_2' => $dependentOptions['referencias_2'],
            ],
            'metrics' => [
                'personas' => $filtered->map(fn (Programa $row) => $this->personKey($row))->unique()->count(),
                'proyectos' => $filtered->pluck('operador')->filter()->unique()->count(),
                'municipios' => $filtered->pluck('municipio')->filter()->unique()->count(),
                'asignaciones' => $filtered->count(),
                'referencias_1' => $filtered->pluck('ref_1')->filter()->unique()->count(),
                'referencias_2' => $filtered->pluck('ref_2')->filter()->unique()->count(),
            ],
            'peopleByProject' => $peopleByProject,
            'projectsByMunicipality' => $projectsByMunicipality,
            'peopleByReference1' => $peopleByReference1,
            'peopleByReference2' => $peopleByReference2,
            'peopleDetails' => $peopleDetails,
            'assignmentDetails' => $assignmentDetails,
            'details' => $details,
            'source' => ProgramaFuente::query()->where('activa', true)->first(),
            'canSync' => $this->canSync(),
            'canConfigureGoogle' => $this->canConfigureGoogle(),
        ]);
    }

    public function sync(ProgramasGoogleSyncService $service)
    {
        $this->authorizeModule();
        abort_unless($this->canSync(), 403, 'No tienes permisos para sincronizar Programas.');

        $source = ProgramaFuente::query()->where('activa', true)->firstOrFail();
        try {
            $result = $service->sincronizar($source);
            $message = "Sincronización completada: {$result['creados']} creados, {$result['actualizados']} actualizados y ".count($result['errores']).' observaciones.';

            return back()->with($result['errores'] === [] ? 'success' : 'warning', $message);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No fue posible sincronizar: '.$e->getMessage());
        }
    }

    private function personKey(Programa $programa): string
    {
        $document = trim((string) $programa->cedula);
        if ($document !== '' && !in_array(ProgramaMunicipios::normalize($document), ['NA', 'N/A'], true)) {
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

    private function authorizeModule(): void
    {
        $user = backpack_user();
        abort_unless($user && ($user->hasAnyRole(['admin', 'diana', 'programas']) || in_array((int) $user->role_id, [1, 2, 8], true)), 403);
    }

    private function canSync(): bool
    {
        $user = backpack_user();
        return (bool) ($user && ($user->hasAnyRole(['admin', 'programas']) || in_array((int) $user->role_id, [1, 8], true)));
    }

    private function canConfigureGoogle(): bool
    {
        $user = backpack_user();
        return (bool) ($user && ($user->hasAnyRole(['admin', 'diana']) || in_array((int) $user->role_id, [1, 2], true)));
    }
}
