<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Persona;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\DB;

class PersonasNivelChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Persona::select('nivel_academico_id', DB::raw('COUNT(*) as total'))
            ->groupBy('nivel_academico_id')
            ->with('nivelAcademico')
            ->get();

        $labels = $data->pluck('nivelAcademico.nombre');
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
