<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Seguimiento;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\DB;

class ContratosSecretariaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();

        $data = Seguimiento::select('secretaria_id', DB::raw('SUM(valor_total_contrato) as total'))
            ->groupBy('secretaria_id')
            ->with('secretaria')
            ->get();

        $labels = $data->pluck('secretaria.nombre');
        $values = $data->pluck('total');

        $this->chart->labels($labels);
        $this->chart->dataset('Valor total contratado por Secretaría', 'pie', $values)
            ->options(['backgroundColor' => ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796']]);
    }
}
