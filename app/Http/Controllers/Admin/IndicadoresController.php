<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\Seguimiento;
use Illuminate\Support\Facades\DB;
use Backpack\CRUD\app\Library\Widget;

class IndicadoresController extends Controller
{
    public function index()
    {
        // === A. INDICADORES DE PERSONAS ===
        $totalPersonas = Persona::count();

        $personasConContrato = Persona::whereHas('seguimientos', function ($q) {
            $q->where('estado_contrato_id', 1); // 1 = activo
        })->count();

        $personasSinSeguimiento = Persona::doesntHave('seguimientos')->count();

        $porNivelAcademico = Persona::select('nivel_academico_id', DB::raw('COUNT(*) as total'))
            ->groupBy('nivel_academico_id')
            ->with('nivelAcademico')
            ->get();

        $porReferencia = DB::table('persona_referencia')
            ->select('referencia_id', DB::raw('COUNT(persona_id) as total'))
            ->groupBy('referencia_id')
            ->get();

        // === B. CONTRATOS / SEGUIMIENTOS ===
        $valorPorSecretaria = Seguimiento::select('secretaria_id', 'anio', DB::raw('SUM(valor_total_contrato) as total'))
            ->groupBy('secretaria_id', 'anio')
            ->with('secretaria')
            ->get();

        $contratosActivos = Seguimiento::where('estado_contrato_id', 1)->count();
        $contratosFinalizados = Seguimiento::where('estado_contrato_id', 2)->count();
        $conAdicion = Seguimiento::where('adicion', 'SI')->count();
        $totalContratos = Seguimiento::count();

        $tiempoPromedioEjecucion = Seguimiento::avg('tiempo_total_ejecucion_dias');

        $demoras = Seguimiento::select(
            DB::raw('AVG(DATEDIFF(fecha_aut_planeacion, fecha_aut_despacho)) as despacho_planeacion'),
            DB::raw('AVG(DATEDIFF(fecha_aut_administrativa, fecha_aut_planeacion)) as planeacion_admin'),
            DB::raw('AVG(DATEDIFF(fecha_acta_inicio, fecha_aut_administrativa)) as admin_inicio')
        )->first();

        // === C. EFICIENCIA GLOBAL ===
        $contratosConDemora = Seguimiento::whereRaw('DATEDIFF(fecha_acta_inicio, fecha_aut_despacho) > 30')->count();
        $porcDemora = $totalContratos ? round($contratosConDemora / $totalContratos * 100, 1) : 0;
        $porcAdicion = $totalContratos ? round($conAdicion / $totalContratos * 100, 1) : 0;

        // === Tarjetas principales (widgets) ===
        Widget::add()->type('div')->class('row')->content([
            Widget::make([
                'type'  => 'progress',
                'value' => $totalPersonas,
                'description' => 'Total Personas',
                'progress' => 100,
                'hint' => round(($personasConContrato / max($totalPersonas,1)) * 100, 1) . '% con contrato',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $totalContratos,
                'description' => 'Total Contratos',
                'progress' => 100,
                'hint' => "{$conAdicion} con adición ({$porcAdicion}%)",
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => number_format($valorPorSecretaria->sum('total'), 0, ',', '.'),
                'description' => 'Valor Total Contratado (YTD)',
                'progress' => 100,
                'hint' => 'Año ' . now()->year,
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => round($tiempoPromedioEjecucion, 1) . ' días',
                'description' => 'Tiempo promedio ejecución',
                'progress' => 100,
                'hint' => "{$porcDemora}% demoran > 30 días",
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
        ]);

        // === Gráficos (widgets) ===
       Widget::add([
    'type' => 'chart',
    'controller' => \App\Http\Controllers\Admin\Charts\PersonasNivelChartController::class,
    'wrapper' => ['class' => 'col-12 col-md-6 mb-3'],
    'title' => 'Personas por Nivel Académico', // ✅ título
]);

Widget::add([
    'type' => 'chart',
    'controller' => \App\Http\Controllers\Admin\Charts\ContratosSecretariaChartController::class,
    'wrapper' => ['class' => 'col-12 col-md-6 mb-3'],
    'title' => 'Valor Total por Secretaría', // ✅ título
]);

        return view(backpack_view('blank'), [
            'title' => '📊 Indicadores Generales',
            'breadcrumbs' => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Indicadores' => false,
            ],
        ]);
    }
}
