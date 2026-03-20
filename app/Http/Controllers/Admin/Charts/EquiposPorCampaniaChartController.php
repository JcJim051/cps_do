<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\EquipoCampania;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EquiposPorCampaniaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Cache::remember('indicadores.chart_equipos_por_campania', 600, function () {
            return EquipoCampania::select('ejercicio_politico_id', DB::raw('COUNT(*) as total'))
                ->groupBy('ejercicio_politico_id')
                ->with('ejercicioPolitico')
                ->orderByDesc('total')
                ->limit(10)
                ->get();
        });

        $labels = $data->map(function ($row) {
            return optional($row->ejercicioPolitico)->nombre ?? 'Sin campaña';
        });
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Equipos', 'bar', $values)
            ->options(['backgroundColor' => '#4e73df']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
