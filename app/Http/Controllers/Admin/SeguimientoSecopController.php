<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seguimiento;
use App\Models\SecopActualizacionSeguimiento;
use App\Services\SecopAplicacionService;
use App\Services\SecopVinculacionService;
use Illuminate\Http\Request;

class SeguimientoSecopController extends Controller
{
    public function candidates(Seguimiento $seguimiento, SecopVinculacionService $service)
    {
        abort_unless($seguimiento->tipo === 'contrato', 422);
        $this->assertEligible($seguimiento);
        try {
            $candidates = $service->candidatosSeguimiento($seguimiento->load('persona'));
            $error = null;
        } catch (\Throwable $e) {
            $candidates = [];
            $error = $e->getMessage();
        }
        return view('admin.prevalidacion.tracking_candidates', compact('seguimiento', 'candidates', 'error'));
    }

    public function link(Request $request, Seguimiento $seguimiento, SecopVinculacionService $service)
    {
        $this->assertEligible($seguimiento);
        $data = $request->validate(['fuente' => ['required', 'string'], 'identificador' => ['required', 'string']]);
        $service->vincularSeguimiento($seguimiento->load('persona'), $data['fuente'], $data['identificador'], backpack_user()?->id);
        return redirect(backpack_url('seguimiento/'.$seguimiento->id.'/show'))->with('success', 'Contrato SECOP vinculado.');
    }

    public function unlink(Seguimiento $seguimiento)
    {
        $seguimiento->vinculoSecop?->delete();
        return back()->with('success', 'Vínculo SECOP retirado.');
    }

    public function refresh(Seguimiento $seguimiento, SecopVinculacionService $service)
    {
        abort_unless($seguimiento->vinculoSecop, 422);
        $result = $service->sincronizar($seguimiento->vinculoSecop, 'manual', backpack_user()?->id);
        return back()->with('success', $this->message($result));
    }

    public function restoreField(Seguimiento $seguimiento, string $field, SecopAplicacionService $service)
    {
        abort_unless($seguimiento->vinculoSecop, 422);
        $result = $service->restoreField($seguimiento->vinculoSecop, $field, backpack_user()?->id);
        return back()->with('success', 'El campo volvió a quedar administrado por SECOP. '.$this->message($result));
    }

    public function revert(Seguimiento $seguimiento, SecopActualizacionSeguimiento $actualizacion, SecopAplicacionService $service)
    {
        abort_unless((int) $actualizacion->seguimiento_id === (int) $seguimiento->id, 404);
        $service->revert($actualizacion, backpack_user()?->id);
        return back()->with('success', 'Actualización revertida. Los campos restaurados quedaron excluidos de la sincronización automática.');
    }

    public function toggleAutomatic(Seguimiento $seguimiento)
    {
        abort_unless($seguimiento->vinculoSecop, 422);
        $link = $seguimiento->vinculoSecop;
        $link->update(['sincronizacion_automatica' => !$link->sincronizacion_automatica]);
        return back()->with('success', $link->sincronizacion_automatica ? 'Sincronización nocturna activada.' : 'Sincronización nocturna pausada.');
    }

    private function message(array $result): string
    {
        return match ($result['resultado'] ?? null) {
            'actualizado' => count($result['cambios'] ?? []).' campos actualizados desde SECOP.',
            'excluido' => 'SECOP fue consultado; todos los cambios están excluidos por edición manual.',
            'sin_datos' => 'SECOP fue consultado, pero no retornó datos aplicables.',
            default => 'SECOP fue consultado sin cambios.',
        };
    }

    private function assertEligible(Seguimiento $seguimiento): void
    {
        $eligible = filled($seguimiento->numero_contrato)
            || ($seguimiento->aut_despacho && $seguimiento->aut_planeacion);

        abort_unless($eligible, 422, 'La conciliación SECOP se habilita después de las autorizaciones 1 y 2.');
    }
}
