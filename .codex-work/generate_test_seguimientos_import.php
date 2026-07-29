<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$outputPath = __DIR__.'/../storage/app/test_seguimientos_import_5_registros.xlsx';

$headers = [
    'id',
    'cedula_o_nit',
    'tipo',
    'secretaria_id',
    'gerencia_id',
    'fuente_id',
    'estado_contrato_id',
    'anio',
    'numero_contrato',
    'fecha_acta_inicio',
    'fecha_finalizacion',
    'tiempo_ejecucion_dias',
    'valor_mensual',
    'valor_total',
    'aut_despacho',
    'aut_planeacion',
    'aut_administrativa',
    'aut_despacho_adicion',
    'aut_planeacion_adicion',
    'aut_administrativa_adicion',
    'fecha_aut_despacho',
    'fecha_aut_planeacion',
    'fecha_aut_administrativa',
    'fecha_aut_despacho_adicion',
    'fecha_aut_planeacion_adicion',
    'fecha_aut_administrativa_adicion',
    'adicion',
    'fecha_acta_inicio_adicion',
    'fecha_finalizacion_adicion',
    'tiempo_ejecucion_dias_adicion',
    'tiempo_total_ejecucion_dias',
    'valor_adicion',
    'valor_total_contrato',
    'evaluacion_id',
    'continua',
    'observaciones_contrato',
];

$rows = [
    [
        '', '1000132305', 'contrato', 1, 1, 4, 3, 2026, 'PRUEBA-CODEX-20260729-01',
        '2026-07-29', '2026-08-28', 30, 2500000, 2500000,
        'SI', 'NO', 'NO', 'NO', 'NO', 'NO',
        '', '', '', '', '', '',
        'NO', '', '', '', 30, 0, 2500000, 1, 'SI',
        'Prueba 1: Aut 1 en SI sin fecha para validar autocompletado.',
    ],
    [
        '', '1000137521', 'contrato', 1, 1, 4, 3, 2026, 'PRUEBA-CODEX-20260729-02',
        '2026-07-29', '2026-09-27', 60, 3100000, 6200000,
        'SI', 'SI', 'NO', 'NO', 'NO', 'NO',
        '', '', '', '', '', '',
        'NO', '', '', '', 60, 0, 6200000, 1, 'SI',
        'Prueba 2: Aut 1 y Aut 2 en SI sin fechas para validar autocompletado.',
    ],
    [
        '', '1000178115', 'contrato', 1, 1, 4, 3, 2026, 'PRUEBA-CODEX-20260729-03',
        '2026-07-29', '2026-10-27', 90, 2800000, 8400000,
        'NO', 'NO', 'NO', 'NO', 'NO', 'NO',
        '', '', '', '', '', '',
        'NO', '', '', '', 90, 0, 8400000, 1, 'SI',
        'Prueba 3: Ninguna autorización activa.',
    ],
    [
        '', '1000256036', 'contrato', 1, 1, 4, 3, 2026, 'PRUEBA-CODEX-20260729-04',
        '2026-07-29', '2026-11-26', 120, 4000000, 12000000,
        'SI', 'NO', 'NO', 'NO', 'NO', 'NO',
        '2026-07-15', '', '', '', '', '',
        'NO', '', '', '', 120, 0, 12000000, 1, 'SI',
        'Prueba 4: Aut 1 en SI con fecha ya diligenciada, no debe sobreescribirse.',
    ],
    [
        '', '1000284918', 'contrato', 1, 1, 4, 3, 2026, 'PRUEBA-CODEX-20260729-05',
        '2026-07-29', '2026-12-28', 152, 3500000, 10500000,
        'SI', 'NO', 'NO', 'SI', 'NO', 'NO',
        '', '', '', '', '', '',
        'SI', '2026-09-01', '2026-10-01', 30, 182, 3500000, 14000000, 1, 'SI',
        'Prueba 5: Aut 1 inicial y de adición en SI sin fechas para validar ambos autocompletados.',
    ],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Plantilla_Seguimientos');
$sheet->fromArray($headers, null, 'A1');
$sheet->fromArray($rows, null, 'A2');

foreach (range('A', $sheet->getHighestColumn()) as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($outputPath);

echo $outputPath.PHP_EOL;
