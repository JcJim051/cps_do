<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seguimiento;
use App\Services\SecopVinculacionService;
use Illuminate\Http\Request;

class SeguimientoSecopController extends Controller
{
    public function candidates(Seguimiento $seguimiento, SecopVinculacionService $service)
    {
        abort_unless($seguimiento->tipo === 'contrato', 422);
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
        $changed = $service->refrescar($seguimiento->vinculoSecop);
        return back()->with('success', $changed ? 'SECOP actualizado.' : 'SECOP consultado sin cambios.');
    }
}
