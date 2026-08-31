<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecopVinculo;
use App\Models\SecopConciliacionLote;
use App\Models\Secretaria;
use App\Services\SecopSincronizacionMasivaService;
use App\Services\SecopVinculacionMasivaService;
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
            'conciliationRun' => SecopConciliacionLote::query()->latest('id')->first(),
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

    public function startExact(Request $request, SecopVinculacionMasivaService $service)
    {
        $data = $request->validate([
            'secretaria_id' => ['nullable', 'integer', 'exists:secretarias,id'],
            'estado_contrato_id' => ['nullable', 'integer', 'exists:estados,id'],
        ]);
        $result = $service->start($data, backpack_user()?->id);
        $message = $result['creado']
            ? "Proceso enviado a segundo plano: {$result['lote']->total_personas} personas por revisar. Ya puedes salir de esta página."
            : 'Ya existe una conciliación masiva en curso. Se mostrará su avance.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'creado' => $result['creado'],
                'lote' => $this->serializeRun($result['lote']),
            ]);
        }

        return redirect()->route('secop.sync.index')->with($result['creado'] ? 'success' : 'warning', $message);
    }

    public function exactStatus()
    {
        $lote = SecopConciliacionLote::query()->latest('id')->first();
        return response()->json(['lote' => $lote ? $this->serializeRun($lote) : null]);
    }

    private function serializeRun(SecopConciliacionLote $lote): array
    {
        return [
            'id' => $lote->id,
            'estado' => $lote->estado,
            'activo' => $lote->isActive(),
            'total_personas' => $lote->total_personas,
            'personas_procesadas' => $lote->personas_procesadas,
            'coincidencias_exactas' => $lote->coincidencias_exactas,
            'vinculos_creados' => $lote->vinculos_creados,
            'errores' => $lote->errores,
            'ultimo_error' => $lote->ultimo_error,
            'porcentaje' => $lote->total_personas > 0
                ? min(100, (int) round(($lote->personas_procesadas / $lote->total_personas) * 100))
                : 100,
            'actualizado' => $lote->updated_at?->timezone('America/Bogota')->format('d/m/Y H:i:s'),
        ];
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
