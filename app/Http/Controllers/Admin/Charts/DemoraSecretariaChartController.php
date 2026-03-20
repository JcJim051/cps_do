<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Seguimiento;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DemoraSecretariaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();
        $year = now()->year;

        $data = Cache::remember("indicadores.chart_demora_secretaria_$year", 600, function () use ($year) {
            return Seguimiento::select(
                    'secretaria_id',
                    DB::raw('AVG(DATEDIFF(fecha_acta_inicio, fecha_aut_despacho)) as promedio')
                )
                ->where('anio', $year)
                ->whereNotNull('fecha_aut_despacho')
                ->whereNotNull('fecha_acta_inicio')
                ->groupBy('secretaria_id')
                ->with('secretaria')
                ->orderByDesc('promedio')
                ->limit(10)
                ->get();
        });

        $labels = $data->map(function ($row) {
            return optional($row->secretaria)->nombre ?? 'Sin secretaría';
        });
        $values = $data->pluck('promedio');

        $this->chart->labels($labels);
        $this->chart->dataset('Días promedio', 'bar', $values)
            ->options(['backgroundColor' => '#f6c23e']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
