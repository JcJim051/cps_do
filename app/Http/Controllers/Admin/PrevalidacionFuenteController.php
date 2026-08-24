<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PrevalidacionFuentesTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PrevalidacionFuentesImport;
use App\Models\GoogleIntegration;
use App\Models\PrevalidacionFuente;
use App\Models\Secretaria;
use App\Services\GoogleSheetsService;
use App\Services\PrevalidacionSyncService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PrevalidacionFuenteController extends Controller
{
    public const MAPPING_FIELDS = [
        'numero_origen' => 'columna_numero_origen', 'dependencia' => 'columna_dependencia', 'anio' => 'columna_anio',
        'gerencia' => 'columna_gerencia', 'estado' => 'columna_estado', 'nombre_contratista' => 'columna_nombre',
        'cedula_o_nit' => 'columna_cedula', 'celular' => 'columna_celular', 'nivel_academico' => 'columna_nivel_academico',
        'profesion' => 'columna_profesion', 'especializacion' => 'columna_especializacion', 'maestria' => 'columna_maestria',
        'fuente_recursos' => 'columna_fuente_recursos', 'numero_contrato' => 'columna_numero_contrato',
        'fecha_inicio' => 'columna_fecha_inicio', 'fecha_fin' => 'columna_fecha_fin', 'tiempo_dias' => 'columna_tiempo_dias',
        'valor_mensual' => 'columna_valor_mensual', 'valor_total' => 'columna_valor_total', 'adicion' => 'columna_adicion',
        'fecha_inicio_adicion' => 'columna_fecha_inicio_adicion', 'fecha_fin_adicion' => 'columna_fecha_fin_adicion',
        'tiempo_adicion_dias' => 'columna_tiempo_adicion_dias', 'tiempo_total_dias' => 'columna_tiempo_total_dias',
        'valor_adicion' => 'columna_valor_adicion', 'valor_total_contrato' => 'columna_valor_total_contrato',
        'evaluacion' => 'columna_evaluacion', 'continua' => 'columna_continua', 'observaciones' => 'columna_observaciones',
        'sector' => 'columna_sector', 'enlace' => 'columna_enlace', 'programa' => 'columna_programa',
    ];

    public function index()
    {
        $google = GoogleIntegration::find(1);
        $fuentes = PrevalidacionFuente::with('secretaria')->withCount([
            'prevalidaciones' => fn ($query) => $query->where('presente_en_origen', true)->where('estado_origen', 'APROBADO'),
        ])->orderBy('nombre')->get();
        foreach ($fuentes as $fuente) {
            $fuente->cps_vigentes_count = $fuente->prevalidaciones()
                ->whereHas('vinculoSecop.ultimaInstantanea', fn ($q) => $q->whereIn(\DB::raw('UPPER(estado)'), config('prevalidacion.estados_secop_vigentes')))
                ->count();
        }
        return view('admin.prevalidacion.sources.index', [
            'fuentes' => $fuentes,
            'google' => $google,
            'googleConnected' => filled($google?->refresh_token) || filled($google?->access_token),
            'googleConfigured' => filled($google?->oauth_client_id) && filled($google?->oauth_client_secret),
            'googleCallbackUrl' => route('prevalidacion.google.callback'),
        ]);
    }

    public function create()
    {
        return view('admin.prevalidacion.sources.form', ['fuente' => new PrevalidacionFuente(), 'secretarias' => Secretaria::orderBy('nombre')->get()]);
    }

    public function store(Request $request, GoogleSheetsService $sheets)
    {
        $data = $this->validated($request, $sheets);
        PrevalidacionFuente::create($data);
        return redirect()->route('prevalidacion.sources.index')->with('success', 'Fuente creada.');
    }

    public function edit(PrevalidacionFuente $fuente)
    {
        return view('admin.prevalidacion.sources.form', ['fuente' => $fuente, 'secretarias' => Secretaria::orderBy('nombre')->get()]);
    }

    public function update(Request $request, PrevalidacionFuente $fuente, GoogleSheetsService $sheets)
    {
        $fuente->update($this->validated($request, $sheets));
        return redirect()->route('prevalidacion.sources.index')->with('success', 'Fuente actualizada.');
    }

    public function sync(PrevalidacionFuente $fuente, PrevalidacionSyncService $sync)
    {
        try {
            $result = $sync->sincronizar($fuente);
            return back()->with($result['errores'] ? 'warning' : 'success', "Creados: {$result['creados']}; actualizados: {$result['actualizados']}; omitidos: {$result['omitidos']}. ".implode(' | ', $result['errores']));
        } catch (\Throwable $e) {
            $fuente->update(['ultimo_error' => $e->getMessage()]);
            return back()->with('error', 'No fue posible sincronizar: '.$e->getMessage());
        }
    }

    public function syncAll(PrevalidacionSyncService $sync)
    {
        $messages = [];
        foreach (PrevalidacionFuente::where('activa', true)->get() as $fuente) {
            try {
                $result = $sync->sincronizar($fuente);
                $messages[] = "{$fuente->nombre}: {$result['creados']} nuevos, {$result['actualizados']} actualizados";
            } catch (\Throwable $e) {
                $messages[] = "{$fuente->nombre}: ERROR {$e->getMessage()}";
            }
        }
        return back()->with('success', implode(' | ', $messages) ?: 'No hay fuentes activas.');
    }

    public function import(Request $request, GoogleSheetsService $sheets)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        $import = new PrevalidacionFuentesImport($sheets);
        Excel::import($import, $request->file('file'));
        return back()->with($import->errors ? 'warning' : 'success', "{$import->processed} fuentes procesadas. ".implode(' | ', $import->errors));
    }

    public function template()
    {
        return Excel::download(new PrevalidacionFuentesTemplateExport(), 'configuracion_fuentes_prevalidacion.xlsx');
    }

    private function validated(Request $request, GoogleSheetsService $sheets): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'], 'secretaria_id' => ['required', 'exists:secretarias,id'],
            'nit_entidad' => ['required', 'string', 'max:30'], 'spreadsheet_url_o_id' => ['required', 'string'],
            'hoja' => ['required', 'string', 'max:255'], 'fila_encabezados' => ['required', 'integer', 'min:1'],
            'anio_objetivo' => ['required', 'integer', 'between:2000,2100'],
            'columna_clave' => ['nullable', 'string', 'max:255'], 'columna_editado' => ['nullable', 'string', 'max:255'],
        ]);
        $mapping = [];
        foreach (self::MAPPING_FIELDS as $canonical => $input) {
            if ($request->filled($input)) {
                $mapping[$canonical] = trim((string) $request->input($input));
            }
        }
        foreach (['cedula_o_nit', 'nombre_contratista', 'estado', 'anio'] as $required) {
            if (!isset($mapping[$required])) {
                abort(422, "Falta el mapeo obligatorio {$required}.");
            }
        }
        $raw = $validated['spreadsheet_url_o_id'];
        return [
            'nombre' => $validated['nombre'], 'secretaria_id' => $validated['secretaria_id'],
            'nit_entidad' => preg_replace('/\D+/', '', $validated['nit_entidad']),
            'spreadsheet_id' => $sheets->spreadsheetId($raw), 'spreadsheet_url' => str_starts_with($raw, 'http') ? $raw : null,
            'hoja' => $validated['hoja'], 'fila_encabezados' => $validated['fila_encabezados'],
            'anio_objetivo' => $validated['anio_objetivo'],
            'mapeo_columnas' => $mapping, 'columna_clave' => $validated['columna_clave'] ?: 'ID_INTEGRA',
            'columna_estado' => $mapping['estado'], 'columna_editado' => $validated['columna_editado'] ?: 'EDITADO_EN_INTEGRA',
            'activa' => $request->boolean('activa'),
        ];
    }
}
