<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\Seguimiento;
use App\Models\EjercicioPolitico;
use App\Models\EquipoCampania;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Backpack\CRUD\app\Library\Widget;

class IndicadoresController extends Controller
{
    public function index()
    {
        $year = now()->year;
        $cacheTtl = 600; // 10 min

        // === A. INDICADORES DE PERSONAS ===
        $totalPersonas = Cache::remember("indicadores.total_personas", $cacheTtl, function () {
            return Persona::count();
        });

        $personasConContrato = Cache::remember("indicadores.personas_contrato_activo", $cacheTtl, function () {
            return Persona::whereHas('seguimientos', function ($q) {
                $q->where('estado_contrato_id', 1); // 1 = activo
            })->count();
        });

        $personasSinSeguimiento = Cache::remember("indicadores.personas_sin_seguimiento", $cacheTtl, function () {
            return Persona::doesntHave('seguimientos')->count();
        });

        $porNivelAcademico = Cache::remember("indicadores.personas_nivel_academico", $cacheTtl, function () {
            return Persona::select('nivel_academico_id', DB::raw('COUNT(*) as total'))
                ->groupBy('nivel_academico_id')
                ->with('nivelAcademico')
                ->get();
        });

        $porReferencia = Cache::remember("indicadores.personas_por_referencia", $cacheTtl, function () {
            return DB::table('persona_referencia')
                ->select('referencia_id', DB::raw('COUNT(persona_id) as total'))
                ->groupBy('referencia_id')
                ->get();
        });

        $generoStats = Cache::remember("indicadores.personas_genero", $cacheTtl, function () {
            return Persona::select('genero', DB::raw('COUNT(*) as total'))
                ->groupBy('genero')
                ->get();
        });
        $totalGenero = $generoStats->sum('total');
        $hombres = (int)($generoStats->firstWhere('genero', 'Masculino')->total ?? 0);
        $mujeres = (int)($generoStats->firstWhere('genero', 'Femenino')->total ?? 0);
        $porcHombres = $totalGenero ? round($hombres / $totalGenero * 100, 1) : 0;
        $porcMujeres = $totalGenero ? round($mujeres / $totalGenero * 100, 1) : 0;

        // === B. CONTRATOS / SEGUIMIENTOS ===
        $valorPorSecretaria = Cache::remember("indicadores.valor_secretaria_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::select('secretaria_id', DB::raw('SUM(valor_total_contrato) as total'))
                ->where('anio', $year)
                ->groupBy('secretaria_id')
                ->with('secretaria')
                ->get();
        });

        $contratosActivos = Cache::remember("indicadores.contratos_activos_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('estado_contrato_id', 1)
                ->where('anio', $year)
                ->count();
        });
        $contratosFinalizados = Cache::remember("indicadores.contratos_finalizados_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('estado_contrato_id', 2)
                ->where('anio', $year)
                ->count();
        });
        $conAdicion = Cache::remember("indicadores.contratos_con_adicion_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('adicion', 'SI')
                ->where('anio', $year)
                ->count();
        });
        $totalContratos = Cache::remember("indicadores.contratos_total_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('anio', $year)->count();
        });

        $tiempoPromedioEjecucion = Cache::remember("indicadores.tiempo_promedio_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('anio', $year)->avg('tiempo_total_ejecucion_dias');
        });

        $demoras = Cache::remember("indicadores.demoras_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('anio', $year)
                ->whereNotNull('fecha_aut_despacho')
                ->whereNotNull('fecha_aut_planeacion')
                ->whereNotNull('fecha_aut_administrativa')
                ->whereNotNull('fecha_acta_inicio')
                ->select(
                    DB::raw('AVG(DATEDIFF(fecha_aut_planeacion, fecha_aut_despacho)) as despacho_planeacion'),
                    DB::raw('AVG(DATEDIFF(fecha_aut_administrativa, fecha_aut_planeacion)) as planeacion_admin'),
                    DB::raw('AVG(DATEDIFF(fecha_acta_inicio, fecha_aut_administrativa)) as admin_inicio')
                )->first();
        });

        // === C. EFICIENCIA GLOBAL ===
        $contratosConDemora = Cache::remember("indicadores.contratos_demora_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('anio', $year)
                ->whereNotNull('fecha_acta_inicio')
                ->whereNotNull('fecha_aut_despacho')
                ->whereRaw('DATEDIFF(fecha_acta_inicio, fecha_aut_despacho) > 30')
                ->count();
        });
        $porcDemora = $totalContratos ? round($contratosConDemora / $totalContratos * 100, 1) : 0;
        $porcAdicion = $totalContratos ? round($conAdicion / $totalContratos * 100, 1) : 0;

        $tiempoCicloAprobacion = Cache::remember("indicadores.ciclo_aprobacion_$year", $cacheTtl, function () use ($year) {
            return Seguimiento::where('anio', $year)
                ->whereNotNull('fecha_aut_despacho')
                ->whereNotNull('fecha_acta_inicio')
                ->avg(DB::raw('DATEDIFF(fecha_acta_inicio, fecha_aut_despacho)'));
        });

        // === D. EJERCICIOS POLÍTICOS ===
        $campaniasTotal = Cache::remember("indicadores.campanias_total", $cacheTtl, function () {
            return EjercicioPolitico::count();
        });

        $equiposTotal = Cache::remember("indicadores.equipos_total", $cacheTtl, function () {
            return EquipoCampania::count();
        });

        $personasEnEquipos = Cache::remember("indicadores.personas_en_equipos", $cacheTtl, function () {
            return (int) DB::table('equipo_campania_persona')
                ->distinct('persona_id')
                ->count('persona_id');
        });

        $personasSinEquipo = Cache::remember("indicadores.personas_sin_equipo", $cacheTtl, function () {
            return Persona::whereNotIn('id', function ($q) {
                $q->select('persona_id')->from('equipo_campania_persona');
            })->count();
        });

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
                'hint' => 'Año ' . $year,
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
            Widget::make([
                'type'  => 'progress',
                'value' => $hombres,
                'description' => 'Hombres',
                'progress' => 100,
                'hint' => $porcHombres . '%',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $mujeres,
                'description' => 'Mujeres',
                'progress' => 100,
                'hint' => $porcMujeres . '%',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $contratosConDemora,
                'description' => 'Contratos con demora > 30 días',
                'progress' => 100,
                'hint' => 'Año ' . $year,
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => round((float)$tiempoCicloAprobacion, 1) . ' días',
                'description' => 'Ciclo aprobación → acta inicio',
                'progress' => 100,
                'hint' => 'Promedio anual',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $campaniasTotal,
                'description' => 'Campañas activas',
                'progress' => 100,
                'hint' => 'Total campañas',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $equiposTotal,
                'description' => 'Equipos registrados',
                'progress' => 100,
                'hint' => 'Total equipos',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $personasEnEquipos,
                'description' => 'Personas en equipos',
                'progress' => 100,
                'hint' => 'Asignadas a grupos',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
            Widget::make([
                'type'  => 'progress',
                'value' => $personasSinEquipo,
                'description' => 'Personas sin equipo',
                'progress' => 100,
                'hint' => 'Sin grupo asignado',
                'wrapper' => ['class' => 'col-12 col-md-3 mb-3'],
            ]),
        ]);

        // === Gráficos (widgets) ===
        Widget::add()->type('div')->class('row')->content([
            Widget::make([
                'type' => 'chart',
                'controller' => \App\Http\Controllers\Admin\Charts\PersonasNivelChartController::class,
                'wrapper' => ['class' => 'col-12 col-md-4 mb-3'],
                'title' => 'Personas por Nivel Académico', // ✅ título
            ]),
            Widget::make([
                'type' => 'chart',
                'controller' => \App\Http\Controllers\Admin\Charts\DemoraSecretariaChartController::class,
                'wrapper' => ['class' => 'col-12 col-md-4 mb-3'],
                'title' => 'Demora Promedio (Despacho → Acta Inicio)',
            ]),
            Widget::make([
                'type' => 'chart',
                'controller' => \App\Http\Controllers\Admin\Charts\PersonasCasosChartController::class,
                'wrapper' => ['class' => 'col-12 col-md-4 mb-3'],
                'title' => 'Casos Especiales (Personas)',
            ]),
            Widget::make([
                'type' => 'chart',
                'controller' => \App\Http\Controllers\Admin\Charts\EquiposPorCampaniaChartController::class,
                'wrapper' => ['class' => 'col-12 col-md-4 mb-3'],
                'title' => 'Equipos por Campaña',
            ]),
            Widget::make([
                'type' => 'chart',
                'controller' => \App\Http\Controllers\Admin\Charts\PersonasPorCampaniaChartController::class,
                'wrapper' => ['class' => 'col-12 col-md-4 mb-3'],
                'title' => 'Personas por Campaña',
            ]),
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
