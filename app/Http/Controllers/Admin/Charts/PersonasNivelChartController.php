<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Persona;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PersonasNivelChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Cache::remember('indicadores.chart_personas_nivel', 600, function () {
            return Persona::select('nivel_academico_id', DB::raw('COUNT(*) as total'))
                ->groupBy('nivel_academico_id')
                ->with('nivelAcademico')
                ->get();
        });

        $labels = $data->map(function ($row) {
            return optional($row->nivelAcademico)->nombre ?? 'Sin nivel';
        });
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Personas por Nivel Académico', 'bar', $values)
            ->options(['backgroundColor' => '#3490dc']);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => false],
        ]);
    }
}
