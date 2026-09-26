<?php

namespace App\Http\Controllers\Admin;

use App\Services\DatosAbiertosSecopService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                $fullCacheKey = $cacheKey . '_' . ($desde ?: 'all');
                $results = Cache::get($fullCacheKey);
                if ($results === null) {
                    $results = $this->datosAbiertosSecopService->consultarPorDocumento($cedula, $desde !== '' ? $desde : null);
                    $warnings = $this->datosAbiertosSecopService->warnings();
                    if ($warnings === []) {
                        Cache::put($fullCacheKey, $results, 600);
                    } else {
                        $error = implode(' ', $warnings);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('No se pudo completar la consulta manual de Datos Abiertos.', [
                    'documento_hash' => hash('sha256', $cedula),
                    'error' => $e->getMessage(),
                ]);
                $error = 'No se pudo consultar Datos Abiertos. SECOP no respondió desde el servidor; intenta nuevamente en unos minutos.';
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
