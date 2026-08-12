<?php

namespace App\Http\Controllers\Admin;

use App\Services\DatosAbiertosSecopService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConsultaDatosAbiertosController extends Controller
{
    public function __construct(private DatosAbiertosSecopService $datosAbiertosSecopService)
    {
    }

    public function index(Request $request)
    {
        if (!backpack_user() || !backpack_user()->hasAnyRole(['admin', 'diana'])) {
            abort(403);
        }

        $cedula = trim((string) $request->get('cedula'));
        $desde = trim((string) $request->get('desde'));
        $results = null;
        $error = null;

        if ($cedula !== '') {
            $cacheKey = 'consulta_datos_abiertos_' . $cedula;
            try {
                $results = Cache::remember($cacheKey . '_' . ($desde ?: 'all'), 600, function () use ($cedula, $desde) {
                    return $this->datosAbiertosSecopService->consultarPorDocumento($cedula, $desde !== '' ? $desde : null);
                });
            } catch (\Throwable $e) {
                $error = 'No se pudo consultar Datos Abiertos.';
            }
        }

        return view('admin.consulta_datos_abiertos.index', [
            'cedula' => $cedula,
            'desde' => $desde,
            'results' => $results,
            'error' => $error,
        ]);
    }
}
