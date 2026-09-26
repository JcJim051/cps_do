<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SeguimientoExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles
{
    public function __construct(protected Collection $entries)
    {
    }

    public function collection(): Collection
    {
        return $this->entries->map(function ($seguimiento) {
            return [
                'dependencia' => $seguimiento->secretaria?->nombre ?? '',
                'nombre' => $seguimiento->persona?->nombre_contratista ?? '',
                'cedula' => $seguimiento->persona?->cedula_o_nit ?? '',
                'anio' => $seguimiento->anio,
                'estado' => $seguimiento->tipo === 'entrevista'
                    ? ($seguimiento->estado ?? '')
                    : ($seguimiento->estadoContrato?->nombre ?? ''),
                'cto' => $seguimiento->numero_contrato ?? '',
                'inicio' => $seguimiento->fecha_acta_inicio
                    ? ExcelDate::dateTimeToExcel($seguimiento->fecha_acta_inicio)
                    : null,
                'finalizacion' => $seguimiento->fecha_finalizacion_vigente
                    ? ExcelDate::dateTimeToExcel($seguimiento->fecha_finalizacion_vigente)
                    : null,
                'honorarios' => $seguimiento->valor_mensual === null
                    ? null
                    : (float) $seguimiento->valor_mensual,
                'tiempo' => $seguimiento->tiempo_total_vigente_dias,
                'valor_total' => $seguimiento->valor_total_contrato === null
                    ? null
                    : (float) $seguimiento->valor_total_contrato,
                'referencias' => $seguimiento->persona?->referencias
                    ?->pluck('nombre')
                    ->filter()
                    ->unique()
                    ->implode(', ') ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'DEPENDENCIA',
            'NOMBRE',
            'CEDULA',
            'AÑO',
            'ESTADO',
            'CTO',
            'INICIO',
            'FINALIZACIÓN',
            'HONORARIOS',
            'TIEMPO',
            'VALOR TOTAL',
            'REFERENCIAS',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => 'dd/mm/yyyy',
            'H' => 'dd/mm/yyyy',
            'I' => '$#,##0.00',
            'K' => '$#,##0.00',
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['C', 'F'], true) && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
