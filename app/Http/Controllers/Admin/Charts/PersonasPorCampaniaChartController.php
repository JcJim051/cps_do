<?php

namespace App\Http\Controllers\Admin\Charts;

use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PersonasPorCampaniaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Cache::remember('indicadores.chart_personas_por_campania', 600, function () {
            return DB::table('ejercicio_politico_persona')
                ->select('ejercicio_politico_id', DB::raw('COUNT(persona_id) as total'))
                ->groupBy('ejercicio_politico_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get();
        });

        $campanias = Cache::remember('indicadores.chart_personas_por_campania_labels', 600, function () {
            return DB::table('ejercicios_politicos')->pluck('nombre', 'id');
        });

        $labels = $data->map(function ($row) use ($campanias) {
            return $campanias[$row->ejercicio_politico_id] ?? 'Sin campaña';
        });
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Personas', 'bar', $values)
            ->options(['backgroundColor' => '#36b9cc']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
