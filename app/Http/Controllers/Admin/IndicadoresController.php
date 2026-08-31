<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecopConciliacionLote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IndicadoresController extends Controller
{
    public function index(Request $request)
    {
        $years = DB::table('seguimientos')
            ->where('tipo', 'contrato')
            ->whereNotNull('anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio')
            ->map(fn ($year) => (int) $year)
            ->values();

        $defaultYear = $years->contains((int) now()->year) ? (int) now()->year : ($years->first() ?: (int) now()->year);
        $year = (int) $request->integer('anio', $defaultYear);
        if (!$years->contains($year)) {
            $year = $defaultYear;
        }

        $cacheKey = "indicadores.ejecutivo.v3.{$year}";
        if ($request->boolean('actualizar')) {
            Cache::forget($cacheKey);
        }

        $dashboard = Cache::remember($cacheKey, 600, fn () => $this->buildDashboard($year));
        $conciliationRun = SecopConciliacionLote::query()->where('anio', $year)->latest('id')->first();

        return view('admin.indicadores.index', [
            'dashboard' => $dashboard,
            'year' => $year,
            'years' => $years,
            'conciliationRun' => $conciliationRun,
            'title' => 'Indicadores ejecutivos',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Indicadores' => false,
            ],
        ]);
    }

    private function buildDashboard(int $year): array
    {
        $contracts = DB::table('seguimientos as s')
            ->leftJoin('estados as e', 'e.id', '=', 's.estado_contrato_id')
            ->where('s.tipo', 'contrato')
            ->where('s.anio', $year);

        $summary = (clone $contracts)->selectRaw(implode(', ', [
            'COUNT(*) as total',
            'COUNT(DISTINCT s.persona_id) as personas',
            'SUM(COALESCE(NULLIF(s.valor_total_contrato, 0), s.valor_total, 0)) as valor_total',
            "SUM(CASE WHEN UPPER(TRIM(COALESCE(e.nombre,''))) = 'CONTRATADO' THEN 1 ELSE 0 END) as contratados",
            "SUM(CASE WHEN UPPER(TRIM(COALESCE(e.nombre,''))) = 'LIQUIDADO' THEN 1 ELSE 0 END) as liquidados",
            "SUM(CASE WHEN UPPER(TRIM(COALESCE(e.nombre,''))) = 'SUSPENDIDO' THEN 1 ELSE 0 END) as suspendidos",
            "SUM(CASE WHEN UPPER(TRIM(COALESCE(e.nombre,''))) IN ('APROBADO','PENDIENTE APROBACIÓN','VALIDACION HV') THEN 1 ELSE 0 END) as por_gestionar",
            "SUM(CASE WHEN UPPER(TRIM(COALESCE(s.adicion,''))) = 'SI' OR COALESCE(s.valor_adicion,0) > 0 OR COALESCE(s.tiempo_ejecucion_dias_adicion,0) > 0 THEN 1 ELSE 0 END) as con_adicion",
            'SUM(COALESCE(s.valor_adicion, 0)) as valor_adiciones',
            'AVG(NULLIF(COALESCE(s.tiempo_total_calendario_dias, s.tiempo_total_ejecucion_dias, s.tiempo_ejecucion_dias), 0)) as duracion_promedio',
            "SUM(CASE WHEN s.numero_contrato IS NULL OR TRIM(s.numero_contrato) = '' THEN 1 ELSE 0 END) as sin_numero",
            'SUM(CASE WHEN s.fecha_acta_inicio IS NULL OR s.fecha_finalizacion IS NULL THEN 1 ELSE 0 END) as sin_fechas',
            'SUM(CASE WHEN s.valor_total_contrato IS NULL AND s.valor_total IS NULL THEN 1 ELSE 0 END) as sin_valor',
            'SUM(CASE WHEN s.fecha_finalizacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as vencen_30',
            "SUM(CASE WHEN s.fecha_finalizacion < CURDATE() AND UPPER(TRIM(COALESCE(e.nombre,''))) NOT IN ('LIQUIDADO','CANCELADO') THEN 1 ELSE 0 END) as vencidos_sin_cierre",
        ]))->first();

        $linked = DB::table('secop_vinculos as v')
            ->join('seguimientos as s', 's.id', '=', 'v.seguimiento_id')
            ->where('s.tipo', 'contrato')->where('s.anio', $year)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN v.ultimo_error IS NOT NULL THEN 1 ELSE 0 END) as errores, SUM(CASE WHEN v.sincronizacion_automatica = 1 THEN 1 ELSE 0 END) as automaticos')
            ->first();

        $authorizations = DB::table('seguimientos')
            ->where('tipo', 'contrato')->where('anio', $year)
            ->selectRaw('SUM(aut_despacho = 1) as aut1, SUM(aut_planeacion = 1) as aut2, SUM(aut_administrativa = 1) as aut3, SUM(fecha_acta_inicio IS NOT NULL) as iniciados')
            ->first();

        $states = (clone $contracts)
            ->selectRaw("COALESCE(NULLIF(TRIM(e.nombre), ''), 'SIN ESTADO') as nombre, COUNT(*) as total")
            ->groupBy('e.nombre')->orderByDesc('total')->get();

        $secretarias = DB::table('seguimientos as s')
            ->leftJoin('secretarias as sec', 'sec.id', '=', 's.secretaria_id')
            ->leftJoin('secop_vinculos as v', 'v.seguimiento_id', '=', 's.id')
            ->where('s.tipo', 'contrato')->where('s.anio', $year)
            ->selectRaw(implode(', ', [
                "COALESCE(NULLIF(TRIM(sec.nombre), ''), 'Sin secretaría') as nombre",
                'COUNT(*) as contratos',
                'COUNT(DISTINCT s.persona_id) as personas',
                'SUM(COALESCE(NULLIF(s.valor_total_contrato, 0), s.valor_total, 0)) as valor',
                'SUM(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) as vinculados',
                "SUM(CASE WHEN UPPER(TRIM(COALESCE(s.adicion,''))) = 'SI' OR COALESCE(s.valor_adicion,0) > 0 THEN 1 ELSE 0 END) as adiciones",
                'AVG(NULLIF(COALESCE(s.tiempo_total_calendario_dias, s.tiempo_total_ejecucion_dias, s.tiempo_ejecucion_dias), 0)) as dias_promedio',
            ]))
            ->groupBy('s.secretaria_id', 'sec.nombre')
            ->orderByDesc('valor')->limit(12)->get();

        $monthlyRaw = DB::table('seguimientos')
            ->where('tipo', 'contrato')->where('anio', $year)->whereNotNull('fecha_acta_inicio')
            ->selectRaw('MONTH(fecha_acta_inicio) as mes, COUNT(*) as contratos, SUM(COALESCE(NULLIF(valor_total_contrato,0), valor_total,0)) as valor')
            ->groupByRaw('MONTH(fecha_acta_inicio)')->get()->keyBy('mes');
        $monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $monthly = collect(range(1, 12))->map(fn ($month) => [
            'mes' => $monthNames[$month - 1],
            'contratos' => (int) ($monthlyRaw[$month]->contratos ?? 0),
            'valor' => (float) ($monthlyRaw[$month]->valor ?? 0),
        ]);

        $previous = DB::table('seguimientos')
            ->where('tipo', 'contrato')->where('anio', $year - 1)
            ->selectRaw('COUNT(*) as total, SUM(COALESCE(NULLIF(valor_total_contrato,0), valor_total,0)) as valor_total')
            ->first();

        $people = [
            'total' => DB::table('personas')->count(),
            'sin_seguimiento' => DB::table('personas')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('seguimientos')->whereColumn('seguimientos.persona_id', 'personas.id'))->count(),
            'en_equipos' => DB::table('equipo_campania_persona')->distinct()->count('persona_id'),
            'equipos' => DB::table('equipos_campania')->count(),
            'campanias' => DB::table('ejercicios_politicos')->count(),
        ];

        $pendingPrevalidation = DB::table('prevalidaciones_contractuales')
            ->where('anio', $year)->whereNull('seguimiento_id')
            ->whereRaw("UPPER(TRIM(estado)) = 'APROBADO'")->count();

        $total = max(1, (int) $summary->total);
        $value = (float) $summary->valor_total;
        $topValue = (float) ($secretarias->first()->valor ?? 0);

        return [
            'summary' => $summary,
            'linked' => $linked,
            'authorizations' => $authorizations,
            'states' => $states,
            'secretarias' => $secretarias,
            'monthly' => $monthly,
            'previous' => $previous,
            'people' => $people,
            'pending_prevalidation' => $pendingPrevalidation,
            'ratios' => [
                'secop' => round(((int) ($linked->total ?? 0) / $total) * 100, 1),
                'adiciones' => round(((int) $summary->con_adicion / $total) * 100, 1),
                'contratados' => round(((int) $summary->contratados / $total) * 100, 1),
                'numeros' => round((1 - ((int) $summary->sin_numero / $total)) * 100, 1),
                'fechas' => round((1 - ((int) $summary->sin_fechas / $total)) * 100, 1),
                'valores' => round((1 - ((int) $summary->sin_valor / $total)) * 100, 1),
                'concentracion' => $value > 0 ? round(($topValue / $value) * 100, 1) : 0,
            ],
        ];
    }
}
