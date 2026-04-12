<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ConsultaDatosAbiertosController extends Controller
{
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
                    $baseUrl = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';
                    $select = implode(', ', [
                        'nombre_entidad',
                        'departamento',
                        'ciudad',
                        'proceso_de_compra',
                        'estado_contrato',
                        'tipo_de_contrato',
                        'modalidad_de_contratacion',
                        'fecha_de_firma',
                        'fecha_de_inicio_del_contrato',
                        'fecha_de_fin_del_contrato',
                        'valor_del_contrato',
                        'proveedor_adjudicado',
                        'documento_proveedor',
                        'urlproceso',
                        'objeto_del_contrato',
                    ]);
                    $where = "documento_proveedor = '{$cedula}'";
                    if ($desde !== '') {
                        $where .= " AND fecha_de_firma >= '{$desde}T00:00:00.000'";
                    }

                    $response = Http::timeout(12)->get($baseUrl, [
                        '$select' => $select,
                        '$where' => $where,
                        '$order' => 'fecha_de_firma DESC',
                        '$limit' => 1000,
                    ]);

                    if (!$response->ok()) {
                        throw new \Exception('HTTP ' . $response->status());
                    }

                    return $response->json();
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
