<?php

namespace App\Http\Controllers\Admin\Charts;

use App\Models\Seguimiento;
use Backpack\CRUD\app\Http\Controllers\ChartController;
use ConsoleTVs\Charts\Classes\Chartjs\Chart;
use Illuminate\Support\Facades\Cache;

class AutorizacionesPorDiaChartController extends ChartController
{
    public function setup()
    {
        $this->chart = new Chart();
        $year = now()->year;

        $data = Cache::remember("indicadores.chart_autorizaciones_por_dia_{$year}", 600, function () use ($year) {
            $base = Seguimiento::query()
                ->where('tipo', 'contrato')
                ->where('anio', $year);

            $initialAut1 = (clone $base)
                ->whereNotNull('fecha_aut_despacho')
                ->selectRaw('fecha_aut_despacho as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_despacho')
                ->pluck('total', 'fecha');

            $initialAut2 = (clone $base)
                ->whereNotNull('fecha_aut_planeacion')
                ->selectRaw('fecha_aut_planeacion as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_planeacion')
                ->pluck('total', 'fecha');

            $initialAut3 = (clone $base)
                ->whereNotNull('fecha_aut_administrativa')
                ->selectRaw('fecha_aut_administrativa as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_administrativa')
                ->pluck('total', 'fecha');

            $adicionBase = (clone $base)->where('adicion', 'SI');

            $adicionAut1 = (clone $adicionBase)
                ->whereNotNull('fecha_aut_despacho_adicion')
                ->selectRaw('fecha_aut_despacho_adicion as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_despacho_adicion')
                ->pluck('total', 'fecha');

            $adicionAut2 = (clone $adicionBase)
                ->whereNotNull('fecha_aut_planeacion_adicion')
                ->selectRaw('fecha_aut_planeacion_adicion as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_planeacion_adicion')
                ->pluck('total', 'fecha');

            $adicionAut3 = (clone $adicionBase)
                ->whereNotNull('fecha_aut_administrativa_adicion')
                ->selectRaw('fecha_aut_administrativa_adicion as fecha, COUNT(*) as total')
                ->groupBy('fecha_aut_administrativa_adicion')
                ->pluck('total', 'fecha');

            return [
                'initial_aut1' => $initialAut1,
                'initial_aut2' => $initialAut2,
                'initial_aut3' => $initialAut3,
                'adicion_aut1' => $adicionAut1,
                'adicion_aut2' => $adicionAut2,
                'adicion_aut3' => $adicionAut3,
            ];
        });

        $labels = collect()
            ->merge(array_keys($data['initial_aut1']->toArray()))
            ->merge(array_keys($data['initial_aut2']->toArray()))
            ->merge(array_keys($data['initial_aut3']->toArray()))
            ->merge(array_keys($data['adicion_aut1']->toArray()))
            ->merge(array_keys($data['adicion_aut2']->toArray()))
            ->merge(array_keys($data['adicion_aut3']->toArray()))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $series = function ($map) use ($labels) {
            return $labels->map(fn ($date) => (int) ($map[$date] ?? 0))->values();
        };

        $this->chart->labels($labels->toArray());

        $this->chart->dataset('Inicial - Aut 1', 'line', $series($data['initial_aut1'])->toArray())
            ->options(['borderColor' => '#1d4ed8', 'backgroundColor' => '#1d4ed8', 'fill' => false]);
        $this->chart->dataset('Inicial - Aut 2', 'line', $series($data['initial_aut2'])->toArray())
            ->options(['borderColor' => '#0ea5e9', 'backgroundColor' => '#0ea5e9', 'fill' => false]);
        $this->chart->dataset('Inicial - Aut 3', 'line', $series($data['initial_aut3'])->toArray())
            ->options(['borderColor' => '#06b6d4', 'backgroundColor' => '#06b6d4', 'fill' => false]);

        $this->chart->dataset('Adicion - Aut 1', 'line', $series($data['adicion_aut1'])->toArray())
            ->options(['borderColor' => '#7c3aed', 'backgroundColor' => '#7c3aed', 'fill' => false, 'borderDash' => [6, 3]]);
        $this->chart->dataset('Adicion - Aut 2', 'line', $series($data['adicion_aut2'])->toArray())
            ->options(['borderColor' => '#a855f7', 'backgroundColor' => '#a855f7', 'fill' => false, 'borderDash' => [6, 3]]);
        $this->chart->dataset('Adicion - Aut 3', 'line', $series($data['adicion_aut3'])->toArray())
            ->options(['borderColor' => '#d946ef', 'backgroundColor' => '#d946ef', 'fill' => false, 'borderDash' => [6, 3]]);

        $this->chart->options([
            'responsive' => true,
            'legend' => ['display' => true],
            'scales' => [
                'yAxes' => [[
                    'ticks' => [
                        'beginAtZero' => true,
                        'precision' => 0,
                    ],
                ]],
            ],
        ]);
    }
}

