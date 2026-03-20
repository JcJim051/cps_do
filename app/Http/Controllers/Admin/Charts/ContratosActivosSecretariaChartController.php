<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Seguimiento;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContratosActivosSecretariaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();
        $year = now()->year;

        $data = Cache::remember("indicadores.chart_contratos_activos_secretaria_$year", 600, function () use ($year) {
            return Seguimiento::select('secretaria_id', DB::raw('COUNT(*) as total'))
                ->where('anio', $year)
                ->where('estado_contrato_id', 1)
                ->groupBy('secretaria_id')
                ->with('secretaria')
                ->orderByDesc('total')
                ->limit(10)
                ->get();
        });

        $labels = $data->map(function ($row) {
            return optional($row->secretaria)->nombre ?? 'Sin secretaría';
        });
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Contratos activos', 'bar', $values)
            ->options(['backgroundColor' => '#1cc88a']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
