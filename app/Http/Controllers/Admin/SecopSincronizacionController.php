<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecopVinculo;
use App\Models\SecopConciliacionLote;
use App\Models\SecopAuditoriaHallazgo;
use App\Models\SecopAuditoriaLote;
use App\Models\Secretaria;
use App\Services\SecopSincronizacionMasivaService;
use App\Services\SecopVinculacionMasivaService;
use App\Services\SecopAuditoriaService;
use App\Services\SecopAlcanceEntidadService;
use Illuminate\Http\Request;

class SecopSincronizacionController extends Controller
{
    public function index(
        Request $request,
        SecopSincronizacionMasivaService $service,
        SecopAlcanceEntidadService $alcanceEntidades,
    )
    {
        $year = (int) $request->integer('anio', 2026);
        abort_unless($year === 2026, 422, 'El despliegue inicial está limitado a la vigencia 2026.');
        $query = $this->query($request, $year)->with(['seguimiento.persona', 'seguimiento.secretaria', 'seguimiento.estadoContrato', 'ultimaInstantanea']);
        $links = $query->get();
        $summary = $request->boolean('simular') ? $service->run($links, 'simulacion', backpack_user()?->id, true) : null;
        $auditRuns = SecopAuditoriaLote::query()->latest('id')->limit(12)->get();
        $latestAuditRun = $auditRuns->first();
        $requestedAuditId = $request->integer('auditoria_lote');
        $auditRun = $requestedAuditId
            ? $auditRuns->firstWhere('id', $requestedAuditId)
            : $latestAuditRun;
        $auditRun ??= $latestAuditRun;
        $auditBase = $auditRun
            ? SecopAuditoriaHallazgo::query()->where('lote_id', $auditRun->id)
            : SecopAuditoriaHallazgo::query()->whereRaw('1 = 0');
        $auditFindingsQuery = (clone $auditBase)
            ->when($request->filled('auditoria_tipo'), fn ($q) => $q->where('tipo', $request->string('auditoria_tipo')->toString()))
            ->when($request->filled('auditoria_persona'), function ($q) use ($request) {
                $term = trim($request->string('auditoria_persona')->toString());
                $q->whereHas('persona', fn ($personQuery) => $personQuery
                    ->where('nombre_contratista', 'like', "%{$term}%")
                    ->orWhere('cedula_o_nit', 'like', "%{$term}%"));
            })
            ->when($request->filled('auditoria_entidad'), fn ($q) => $q->where('nombre_entidad', $request->string('auditoria_entidad')->toString()))
            ->when($request->filled('auditoria_estado'), fn ($q) => $q->where('estado_secop', $request->string('auditoria_estado')->toString()))
            ->when($request->filled('auditoria_fuente'), fn ($q) => $q->where('fuente_secop', $request->string('auditoria_fuente')->toString()));

        return view('admin.secop_sync.index', [
            'links' => $links, 'summary' => $summary, 'year' => $year,
            'secretarias' => Secretaria::query()->orderBy('nombre')->get(),
            'estados' => \App\Models\Estados::query()->orderBy('nombre')->get(),
            'conciliationRun' => SecopConciliacionLote::query()->latest('id')->first(),
            'auditRun' => $auditRun,
            'latestAuditRun' => $latestAuditRun,
            'auditRuns' => $auditRuns,
            'auditMetrics' => $this->auditMetrics($auditRun),
            'auditFindings' => $auditRun ? $auditFindingsQuery
                ->with(['persona', 'seguimiento'])
                ->latest('id')->paginate(100, ['*'], 'auditoria_page')->withQueryString() : collect(),
            'auditEntities' => (clone $auditBase)->whereNotNull('nombre_entidad')->distinct()->orderBy('nombre_entidad')->pluck('nombre_entidad'),
            'auditStates' => (clone $auditBase)->whereNotNull('estado_secop')->distinct()->orderBy('estado_secop')->pluck('estado_secop'),
            'auditSources' => (clone $auditBase)->whereNotNull('fuente_secop')->distinct()->orderBy('fuente_secop')->pluck('fuente_secop'),
            'auditScope' => $alcanceEntidades->configuracion(),
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

    public function startAudit(Request $request, SecopAuditoriaService $service)
    {
        $data = $request->validate([
            'alcance' => ['required', 'in:persona,todas'],
            'persona' => ['nullable', 'string', 'max:120', 'required_if:alcance,persona'],
        ]);
        $search = $data['alcance'] === 'persona' ? trim($data['persona']) : null;
        $result = $service->start(backpack_user()?->id, $search);
        $scope = $result['lote']->filtro_persona
            ? $result['lote']->total_personas.' persona(s) coincidente(s) con “'.$result['lote']->filtro_persona.'”'
            : 'todas las personas con documento';

        return response()->json([
            'message' => $result['creado']
                ? 'Auditoría 2026 iniciada para '.$scope.'.'
                : 'Ya existe una auditoría en curso; no se inició otra.',
            'creado' => $result['creado'],
            'lote' => $this->serializeAudit($result['lote']),
        ]);
    }

    public function auditStatus()
    {
        $lote = SecopAuditoriaLote::query()->latest('id')->first();
        return response()->json(['lote' => $lote ? $this->serializeAudit($lote) : null]);
    }

    private function serializeAudit(SecopAuditoriaLote $lote): array
    {
        $metrics = $this->auditMetrics($lote);
        return [
            'id' => $lote->id,
            'estado' => $lote->estado,
            'activo' => $lote->isActive(),
            'total_personas' => $lote->total_personas,
            'personas_procesadas' => $lote->personas_procesadas,
            'solo_integra' => $lote->solo_integra,
            'solo_secop' => $lote->solo_secop,
            'requiere_revision' => $lote->requiere_revision,
            'coincidencias_exactas' => $lote->coincidencias_exactas,
            'alertas_secop' => $lote->alertas_secop,
            'personas_solo_integra' => $metrics['solo_integra']['personas'],
            'personas_solo_secop' => $metrics['solo_secop']['personas'],
            'personas_requiere_revision' => $metrics['requiere_revision']['personas'],
            'personas_coincidencia_exacta' => $metrics['coincidencia_exacta']['personas'],
            'personas_alerta_secop' => $metrics['alerta_secop']['personas'],
            'criterio_vigencia' => $lote->criterio_vigencia_label,
            'errores' => $lote->errores,
            'ultimo_error' => $lote->ultimo_error,
            'porcentaje' => $lote->total_personas > 0 ? min(100, (int) round($lote->personas_procesadas * 100 / $lote->total_personas)) : 100,
            'actualizado' => $lote->updated_at?->timezone('America/Bogota')->format('d/m/Y H:i:s'),
        ];
    }

    private function auditMetrics(?SecopAuditoriaLote $lote): array
    {
        $types = ['solo_integra', 'solo_secop', 'requiere_revision', 'coincidencia_exacta', 'alerta_secop'];
        return $lote
            ? $lote->metricasPorTipo()
            : collect($types)->mapWithKeys(fn ($type) => [$type => ['personas' => 0, 'registros' => 0]])->all();
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
