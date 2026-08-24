<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PrevalidacionFuentesTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombre', 'secretaria_id', 'nit_entidad', 'spreadsheet_url_o_id', 'hoja', 'fila_encabezados',
            'columna_clave', 'columna_cedula', 'columna_nombre', 'columna_dependencia', 'columna_gerencia',
            'columna_programa', 'columna_estado', 'columna_anio', 'columna_numero_contrato',
            'columna_fecha_inicio', 'columna_fecha_fin', 'columna_tiempo_dias', 'columna_valor_mensual',
            'columna_valor_total', 'columna_observaciones', 'columna_editado', 'activa',
        ];
    }

    public function array(): array
    {
        return [[
            'AIM', 27, '000000000', 'https://docs.google.com/spreadsheets/d/ID/edit', 'Contratistas', 1,
            'ID', 'CEDULA', 'NOMBRE', 'DEPENDENCIA', 'GERENCIA', 'PROGRAMA', 'ESTADO', 'AÑO',
            'NUMERO CONTRATO', 'FECHA INICIO', 'FECHA FIN', 'TIEMPO', 'VALOR MENSUAL', 'VALOR TOTAL',
            'OBSERVACIONES', 'EDITADO_EN_INTEGRA', 'SI',
        ]];
    }
}
