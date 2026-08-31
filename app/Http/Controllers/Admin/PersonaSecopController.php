<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Services\SecopConciliacionService;
use App\Services\SecopVinculacionService;
use App\Services\SecopSincronizacionMasivaService;

class PersonaSecopController extends Controller
{
    public function sync(Persona $persona, SecopSincronizacionMasivaService $service)
    {
        $links = \App\Models\SecopVinculo::query()
            ->whereHas('seguimiento', fn ($q) => $q->where('persona_id', $persona->id)->where('anio', 2026))
            ->with(['seguimiento', 'ultimaInstantanea'])->get();
        $result = $service->run($links, 'persona', backpack_user()?->id);
        return back()->with('success', "SECOP: {$result['actualizados']} actualizados, {$result['sin_cambios']} sin cambios, {$result['excluidos']} excluidos, {$result['errores']} errores.");
    }

    public function linkExact(
        Persona $persona,
        SecopConciliacionService $conciliacion,
        SecopVinculacionService $vinculacion,
    ) {
        $result = $conciliacion->conciliarPersona($persona);
        $linked = 0;
        $errors = [];

        foreach ($result['filas']->where('seguro', true) as $row) {
            $candidate = $row['candidato'];
            if (!$candidate || $row['seguimiento']->vinculoSecop) {
                continue;
            }

            try {
                $vinculacion->vincularSeguimiento(
                    $row['seguimiento']->load('persona'),
                    $candidate['fuente_codigo'],
                    (string) $candidate['identificador_externo'],
                    backpack_user()?->id,
                );
                $linked++;
            } catch (\Throwable $e) {
                $errors[] = 'Seguimiento '.$row['seguimiento']->id.': '.$e->getMessage();
            }
        }

        $redirect = redirect(backpack_url('persona/'.$persona->id.'/show'));
        if ($errors !== []) {
            return $redirect->with('error', $linked.' vínculos creados. '.implode(' ', $errors));
        }

        return $redirect->with('success', $linked.' coincidencias exactas vinculadas con SECOP.');
    }
}
