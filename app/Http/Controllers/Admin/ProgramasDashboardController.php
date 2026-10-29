<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProgramasAnalysisExport;
use App\Http\Controllers\Controller;
use App\Models\ProgramaFuente;
use App\Services\ProgramasAnalysisService;
use App\Services\ProgramasGoogleSyncService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProgramasDashboardController extends Controller
{
    public function index(Request $request, ProgramasAnalysisService $analysis)
    {
        $this->authorizeModule();

        $dataset = $analysis->resolve($request->query());
        $summary = $analysis->summarize($dataset['analysisRows']);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $details = new LengthAwarePaginator(
            $dataset['detailRows']->forPage($page, $perPage)->values(),
            $dataset['detailRows']->count(),
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
            'filters' => $dataset['filters'],
            'options' => $dataset['options'],
            ...$summary,
            'details' => $details,
            'source' => ProgramaFuente::query()->where('activa', true)->first(),
            'canSync' => $this->canSync(),
            'canConfigureGoogle' => $this->canConfigureGoogle(),
        ]);
    }

    public function export(Request $request, ProgramasAnalysisService $analysis)
    {
        $this->authorizeModule();

        $dataset = $analysis->resolve($request->query());
        $summary = $analysis->summarize($dataset['detailRows']);
        $generatedAt = now('America/Bogota');

        return Excel::download(
            new ProgramasAnalysisExport($dataset['detailRows'], $dataset['filters'], $summary, $generatedAt),
            'programas_analisis_'.$generatedAt->format('Y-m-d_Hi').'.xlsx'
        );
    }

    public function detail(Request $request, ProgramasAnalysisService $analysis)
    {
        $this->authorizeModule();

        $validated = $request->validate([
            'dimension' => ['required', Rule::in(['operador', 'municipio', 'referencia_1', 'referencia_2'])],
            'valor' => ['required', 'string', 'max:500'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'operador' => ['nullable', 'string', 'max:255'],
            'perfil' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:80'],
            'referencia_1' => ['nullable', 'string', 'max:500'],
            'referencia_2' => ['nullable', 'string', 'max:500'],
            'buscar' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filterInput = collect($validated)->only([
            'municipio', 'operador', 'perfil', 'estado', 'referencia_1', 'referencia_2',
        ])->all();
        $dataset = $analysis->resolve($filterInput);
        $rows = $analysis->segment($dataset['analysisRows'], $validated['dimension'], $validated['valor']);
        $rows = $analysis->search($rows, $validated['buscar'] ?? '');
        $metrics = $analysis->summarize($rows)['metrics'];

        $perPage = 25;
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min((int) ($validated['page'] ?? 1), $lastPage);
        $pageRows = $rows->forPage($page, $perPage)->values()->map(fn ($row) => [
            'persona' => $row->nombre_apellidos,
            'cedula' => $row->cedula,
            'municipio' => $row->municipio,
            'proyecto' => $row->operador,
            'perfil' => $row->perfil,
            'institucion' => $row->institucion,
            'referencia_1' => $row->ref_1,
            'referencia_2' => $row->ref_2,
            'estado' => $row->estado,
        ]);

        $dimensionLabel = [
            'operador' => 'Proyecto',
            'municipio' => 'Municipio',
            'referencia_1' => 'Referencia 1',
            'referencia_2' => 'Referencia 2',
        ][$validated['dimension']];

        return response()->json([
            'title' => $dimensionLabel.': '.$validated['valor'],
            'personas' => $metrics['personas'],
            'asignaciones' => $metrics['asignaciones'],
            'rows' => $pageRows,
            'pagination' => [
                'page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
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
