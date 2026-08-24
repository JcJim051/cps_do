<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gerencia;
use App\Models\PrevalidacionContractual;
use App\Models\PrevalidacionFuente;
use App\Services\PrevalidacionPromocionService;
use App\Services\PrevalidacionSyncService;
use App\Services\SecopVinculacionService;
use Illuminate\Http\Request;

class PrevalidacionContractualController extends Controller
{
    public function index(Request $request)
    {
        $query = PrevalidacionContractual::query()->where('presente_en_origen', true)
            ->where('estado_origen', 'APROBADO')
            ->whereNull('seguimiento_id')
            ->with(['fuente', 'secretaria', 'gerencia', 'persona', 'seguimiento', 'vinculoSecop.ultimaInstantanea']);

        foreach (['fuente_id', 'secretaria_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q->where('cedula_o_nit', 'like', "%{$term}%")
                ->orWhere('nombre_contratista', 'like', "%{$term}%")
                ->orWhere('nombre_reportado', 'like', "%{$term}%")
                ->orWhere('clave_externa', 'like', "%{$term}%")
                ->orWhere('numero_contrato_planeado', 'like', "%{$term}%"));
        }
        if ($request->boolean('nuevas')) {
            $query->whereNull('persona_id');
        }
        if ($request->boolean('conflictos')) {
            $query->whereNotNull('conflictos');
        }
        if ($request->boolean('sin_secop')) {
            $query->doesntHave('vinculoSecop');
        }
        if ($request->boolean('sin_promover')) {
            $query->whereNull('seguimiento_id');
        }
        if ($request->filled('vigencia')) {
            $year = (int) $request->input('vigencia');
            $from = "{$year}-01-01";
            $to = "{$year}-12-31";
            $query->where(function ($q) use ($year, $from, $to) {
                $q->where('anio', $year)
                    ->orWhere(fn ($dates) => $dates->whereDate('fecha_inicio_planeada', '<=', $to)
                        ->whereDate('fecha_fin_planeada', '>=', $from))
                    ->orWhereHas('vinculoSecop.instantaneas', fn ($dates) => $dates
                        ->whereDate('fecha_inicio', '<=', $to)->whereDate('fecha_fin', '>=', $from));
            });
        }
        if ($request->filled('secop_estado')) {
            $state = $request->string('secop_estado');
            $query->whereHas('vinculoSecop.instantaneas', fn ($q) => $q->where('estado', 'like', "%{$state}%")->orWhere('fase', 'like', "%{$state}%"));
        }

        $metricQuery = PrevalidacionContractual::query()->where('presente_en_origen', true)
            ->where('estado_origen', 'APROBADO')->whereNull('seguimiento_id');

        return view('admin.prevalidacion.index', [
            'records' => $query->orderByDesc('fila_origen')->paginate(30)->withQueryString(),
            'fuentes' => PrevalidacionFuente::query()->orderBy('nombre')->get(),
            'metrics' => [
                'aprobados' => (clone $metricQuery)->count(),
                'por_enviar' => (clone $metricQuery)->whereNull('seguimiento_id')->count(),
                'drive_pendientes' => PrevalidacionContractual::query()->whereNotNull('seguimiento_id')
                    ->where('drive_sincronizacion_pendiente', true)->count(),
            ],
        ]);
    }

    public function show(PrevalidacionContractual $prevalidacion, SecopVinculacionService $secop)
    {
        if ($prevalidacion->seguimiento_id) {
            return redirect(backpack_url('seguimiento/'.$prevalidacion->seguimiento_id.'/show'))
                ->with('success', 'Esta intención ya fue enviada a Seguimiento.');
        }
        $this->ensureApproved($prevalidacion);
        $prevalidacion->load(['fuente', 'secretaria', 'gerencia', 'persona', 'seguimiento', 'vinculoSecop.ultimaInstantanea', 'eventos' => fn ($q) => $q->latest()->limit(20)]);
        $refreshError = null;
        if ($prevalidacion->vinculoSecop && (!$prevalidacion->vinculoSecop->ultima_consulta_at || $prevalidacion->vinculoSecop->ultima_consulta_at->lt(now()->subMinutes(10)))) {
            try {
                $secop->refrescar($prevalidacion->vinculoSecop);
                $prevalidacion->load('vinculoSecop.ultimaInstantanea');
            } catch (\Throwable $e) {
                $refreshError = $e->getMessage();
            }
        }

        return view('admin.prevalidacion.show', compact('prevalidacion', 'refreshError'));
    }

    public function edit(PrevalidacionContractual $prevalidacion)
    {
        $this->ensureApproved($prevalidacion);
        return view('admin.prevalidacion.edit', [
            'prevalidacion' => $prevalidacion,
            'gerencias' => Gerencia::query()->where('secretaria_id', $prevalidacion->secretaria_id)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, PrevalidacionContractual $prevalidacion, PrevalidacionSyncService $sync)
    {
        $this->ensureApproved($prevalidacion);
        $data = $request->validate([
            'nombre_contratista' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'in:PENDIENTE,CAMBIO,APROBADO'],
            'gerencia_id' => ['nullable', 'exists:gerencias,id'],
            'programa_origen' => ['nullable', 'string', 'max:255'],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'numero_contrato_planeado' => ['nullable', 'string', 'max:255'],
            'fecha_inicio_planeada' => ['nullable', 'date'],
            'fecha_fin_planeada' => ['nullable', 'date', 'after_or_equal:fecha_inicio_planeada'],
            'tiempo_planeado_dias' => ['nullable', 'integer', 'min:0'],
            'valor_mensual_planeado' => ['nullable', 'numeric', 'min:0'],
            'valor_total_planeado' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $changed = collect(array_keys($data))->filter(fn ($field) => $prevalidacion->{$field} != $data[$field])->values()->all();
        $prevalidacion->fill($data);
        if (in_array('nombre_contratista', $changed, true)) {
            $prevalidacion->nombre_requiere_revision = false;
            $changed[] = 'nombre_requiere_revision';
        }
        if ($changed) {
            $sync->markLocalEdit($prevalidacion, $changed, backpack_user()?->id);
        }

        if ($prevalidacion->fresh()->estado_origen !== 'APROBADO') {
            return redirect()->route('prevalidacion.index')->with('success', 'Estado actualizado en el Drive. El registro salió de la bandeja porque ya no está aprobado.');
        }
        return redirect()->route('prevalidacion.show', $prevalidacion)->with('success', 'Prevalidación actualizada y notificada al cuadro de origen.');
    }

    public function bulkState(Request $request, PrevalidacionSyncService $sync)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer'], 'estado' => ['required', 'in:PENDIENTE,CAMBIO,APROBADO']]);
        $ok = 0;
        $errors = [];
        foreach (PrevalidacionContractual::whereIn('id', $data['ids'])->where('presente_en_origen', true)->where('estado_origen', 'APROBADO')->get() as $record) {
            try {
                $record->estado = $data['estado'];
                $sync->markLocalEdit($record, ['estado'], backpack_user()?->id);
                $ok++;
            } catch (\Throwable $e) {
                $errors[] = "{$record->cedula_o_nit}: {$e->getMessage()}";
            }
        }
        return back()->with($errors ? 'warning' : 'success', "{$ok} registros actualizados.".($errors ? ' Errores: '.implode(' | ', $errors) : ''));
    }

    public function acceptDrive(Request $request, PrevalidacionContractual $prevalidacion, PrevalidacionSyncService $sync)
    {
        $this->ensureApproved($prevalidacion);
        $field = (string) $request->validate(['field' => ['required', 'string']])['field'];
        $allowed = [
            'nombre_contratista', 'gerencia_id', 'programa_origen', 'estado', 'anio', 'numero_contrato_planeado',
            'fecha_inicio_planeada', 'fecha_fin_planeada', 'tiempo_planeado_dias', 'valor_mensual_planeado',
            'valor_total_planeado', 'observaciones',
        ];
        abort_unless(in_array($field, $allowed, true), 422, 'Campo no permitido.');
        $sync->acceptDriveValue($prevalidacion, $field, backpack_user()?->id);
        return back()->with('success', 'Se aceptó el valor del cuadro para '.$field.'.');
    }

    public function promote(Request $request, PrevalidacionPromocionService $service)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $ok = 0;
        $errors = [];
        $drivePending = 0;
        foreach (PrevalidacionContractual::whereIn('id', $data['ids'])->where('presente_en_origen', true)->where('estado_origen', 'APROBADO')->get() as $record) {
            try {
                $service->promover($record, backpack_user()?->id);
                $ok++;
                if ($record->fresh()->drive_sincronizacion_pendiente) {
                    $drivePending++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$record->cedula_o_nit}: {$e->getMessage()}";
            }
        }
        $message = "{$ok} seguimientos procesados.";
        if ($drivePending) $message .= " {$drivePending} quedaron pendientes de actualizar y bloquear en Drive.";
        if ($errors) $message .= ' Errores: '.implode(' | ', $errors);
        return redirect()->route('prevalidacion.index')
            ->with(($errors || $drivePending) ? 'warning' : 'success', $message);
    }

    public function retryDrive(PrevalidacionSyncService $sync)
    {
        $ok = 0;
        $errors = [];
        foreach (PrevalidacionContractual::query()->whereNotNull('seguimiento_id')
            ->where('drive_sincronizacion_pendiente', true)->with('fuente')->get() as $record) {
            try {
                $sync->moveToTracking($record, backpack_user()?->id);
                $ok++;
            } catch (\Throwable $e) {
                $record->update(['drive_ultimo_error' => mb_substr($e->getMessage(), 0, 65000)]);
                $errors[] = "{$record->clave_externa}: {$e->getMessage()}";
            }
        }
        $message = "{$ok} filas actualizadas y bloqueadas en Drive.";
        if ($errors) $message .= ' Pendientes: '.implode(' | ', array_slice($errors, 0, 10));
        return back()->with($errors ? 'warning' : 'success', $message);
    }

    public function candidates(PrevalidacionContractual $prevalidacion, SecopVinculacionService $service)
    {
        $this->ensureApproved($prevalidacion);
        try {
            $candidates = $service->candidatos($prevalidacion->load('fuente'));
            $error = null;
        } catch (\Throwable $e) {
            $candidates = [];
            $error = $e->getMessage();
        }
        return view('admin.prevalidacion.candidates', compact('prevalidacion', 'candidates', 'error'));
    }

    public function link(Request $request, PrevalidacionContractual $prevalidacion, SecopVinculacionService $service)
    {
        $this->ensureApproved($prevalidacion);
        $data = $request->validate(['fuente' => ['required', 'string'], 'identificador' => ['required', 'string']]);
        $service->vincular($prevalidacion->load('fuente'), $data['fuente'], $data['identificador'], backpack_user()?->id);
        return redirect()->route('prevalidacion.show', $prevalidacion)->with('success', 'Registro SECOP vinculado.');
    }

    public function unlink(PrevalidacionContractual $prevalidacion, SecopVinculacionService $service)
    {
        $this->ensureApproved($prevalidacion);
        $service->desvincular($prevalidacion, backpack_user()?->id);
        return back()->with('success', 'Vínculo SECOP retirado; el contrato vuelve a estar disponible.');
    }

    public function refresh(PrevalidacionContractual $prevalidacion, SecopVinculacionService $service)
    {
        $this->ensureApproved($prevalidacion);
        abort_unless($prevalidacion->vinculoSecop, 422, 'No existe vínculo SECOP.');
        $changed = $service->refrescar($prevalidacion->vinculoSecop);
        return back()->with('success', $changed ? 'SECOP actualizado; se guardó una nueva instantánea.' : 'SECOP consultado sin cambios.');
    }

    private function ensureApproved(PrevalidacionContractual $record): void
    {
        abort_unless($record->presente_en_origen && $record->estado_origen === 'APROBADO', 404);
    }
}
