<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Persona;
use App\Models\Seguimiento;
use App\Models\Contrato;
use Carbon\Carbon;
use DB;

class DashboardController extends Controller
{
    public function index()
    {
        // === PERSONAS ===
        $totalPersonas = Persona::count();
        $personasContratadas = Persona::whereHas('estadoContrato', function($q){
            $q->where('nombre', 'Contratado');
        })->count();

        $personasPorNivel = Persona::select('nivel_academico_id', DB::raw('count(*) as total'))
            ->groupBy('nivel_academico_id')
            ->with('nivelAcademico')
            ->get();

        $personasPorReferencia = Persona::select('referencia_id', DB::raw('count(*) as total'))
            ->groupBy('referencia_id')
            ->with('referencia')
            ->get();

        // === SEGUIMIENTOS / CONTRATOS ===
        $valorTotalPorSecretaria = Contrato::select('secretaria_id', DB::raw('SUM(valor_total_contrato) as total'))
            ->groupBy('secretaria_id')
            ->with('secretaria')
            ->get();

        $contratosConAdiciones = Contrato::where('adicion', 'SI')
            ->select('secretaria_id', DB::raw('count(*) as total'))
            ->groupBy('secretaria_id')
            ->with('secretaria')
            ->get();

        $tiempoPromedioEjecucion = Contrato::select('secretaria_id', DB::raw('AVG(tiempo_total_ejecucion_dias) as promedio'))
            ->groupBy('secretaria_id')
            ->with('secretaria')
            ->get();

        $tiemposAprobacionPromedio = Contrato::select('secretaria_id', DB::raw('AVG(DATEDIFF(fecha_acta_inicio, fecha_aut_1)) as prom_aut1'))
            ->groupBy('secretaria_id')
            ->with('secretaria')
            ->get();

        $pendientesAut2 = Contrato::where('autorizacion_2', false)->count();
        $pendientesAut3 = Contrato::where('autorizacion_3', false)->count();

        return view('dashboard', compact(
            'totalPersonas',
            'personasContratadas',
            'personasPorNivel',
            'personasPorReferencia',
            'valorTotalPorSecretaria',
            'contratosConAdiciones',
            'tiempoPromedioEjecucion',
            'tiemposAprobacionPromedio',
            'pendientesAut2',
            'pendientesAut3'
        ));
    }
}
