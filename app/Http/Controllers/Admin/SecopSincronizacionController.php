<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecopVinculo;
use App\Models\Secretaria;
use App\Services\SecopSincronizacionMasivaService;
use Illuminate\Http\Request;

class SecopSincronizacionController extends Controller
{
    public function index(Request $request, SecopSincronizacionMasivaService $service)
    {
        $year = (int) $request->integer('anio', 2026);
        abort_unless($year === 2026, 422, 'El despliegue inicial está limitado a la vigencia 2026.');
        $query = $this->query($request, $year)->with(['seguimiento.persona', 'seguimiento.secretaria', 'seguimiento.estadoContrato', 'ultimaInstantanea']);
        $links = $query->get();
        $summary = $request->boolean('simular') ? $service->run($links, 'simulacion', backpack_user()?->id, true) : null;
        return view('admin.secop_sync.index', [
            'links' => $links, 'summary' => $summary, 'year' => $year,
            'secretarias' => Secretaria::query()->orderBy('nombre')->get(),
            'estados' => \App\Models\Estados::query()->orderBy('nombre')->get(),
        ]);
    }

    public function apply(Request $request, SecopSincronizacionMasivaService $service)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $links = SecopVinculo::query()->whereIn('id', $data['ids'])
            ->whereHas('seguimiento', fn ($q) => $q->where('anio', 2026))
            ->with(['seguimiento', 'ultimaInstantanea'])->get();
        $result = $service->run($links, 'masivo', backpack_user()?->id);
        return redirect()->route('secop.sync.index')->with('success', "Lote confirmado: {$result['actualizados']} actualizados, {$result['sin_cambios']} sin cambios, {$result['excluidos']} excluidos, {$result['errores']} errores.");
    }

    private function query(Request $request, int $year)
    {
        return SecopVinculo::query()->whereNotNull('seguimiento_id')
            ->whereHas('seguimiento', function ($q) use ($request, $year) {
                $q->where('anio', $year);
                if ($request->filled('secretaria_id')) $q->where('secretaria_id', $request->integer('secretaria_id'));
                if ($request->filled('estado_contrato_id')) $q->where('estado_contrato_id', $request->integer('estado_contrato_id'));
            });
    }
}
