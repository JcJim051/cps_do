<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Persona;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PersonasCasosChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Cache::remember('indicadores.chart_personas_casos', 600, function () {
            return Persona::select('caso_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('caso_id')
                ->whereHas('caso', function ($q) {
                    $q->whereIn('nombre', ['Embarazo', 'Cancer']);
                })
                ->groupBy('caso_id')
                ->with('caso')
                ->orderByDesc('total')
                ->get();
        });

        $labels = $data->map(function ($row) {
            return optional($row->caso)->nombre ?? '';
        });
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Personas por caso', 'bar', $values)
            ->options(['backgroundColor' => '#e74a3b']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
