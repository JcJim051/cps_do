<?php

namespace Tests\Unit;

use App\Exports\SeguimientoExport;
use App\Models\Estados;
use App\Models\Persona;
use App\Models\Referencia;
use App\Models\Secretaria;
use App\Models\Seguimiento;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

class SeguimientoExportTest extends TestCase
{
    public function test_exporta_el_cuadro_ejecutivo_con_valores_vigentes(): void
    {
        $persona = new Persona([
            'nombre_contratista' => 'PERSONA PRUEBA',
            'cedula_o_nit' => '0012345',
        ]);
        $persona->setRelation('referencias', new EloquentCollection([
            new Referencia(['nombre' => 'Referencia A']),
            new Referencia(['nombre' => 'Referencia B']),
        ]));

        $seguimiento = new Seguimiento([
            'tipo' => 'contrato',
            'anio' => 2026,
            'numero_contrato' => '059-2026',
            'fecha_acta_inicio' => '2026-01-19',
            'fecha_finalizacion' => '2026-06-02',
            'fecha_finalizacion_adicion' => '2026-08-24',
            'valor_mensual' => 5800000,
            'tiempo_ejecucion_dias' => 135,
            'tiempo_total_ejecucion_dias' => 202,
            'tiempo_total_calendario_dias' => 216,
            'valor_total_contrato' => 51038666.67,
        ]);
        $seguimiento->setRelation('persona', $persona);
        $seguimiento->setRelation('secretaria', (new Secretaria())->forceFill(['nombre' => 'AIM']));
        $seguimiento->setRelation('estadoContrato', (new Estados())->forceFill(['nombre' => 'CONTRATADO']));

        $export = new SeguimientoExport(collect([$seguimiento]));
        $row = $export->collection()->first();

        $this->assertSame([
            'DEPENDENCIA', 'NOMBRE', 'CEDULA', 'AÑO', 'ESTADO', 'CTO',
            'INICIO', 'FINALIZACIÓN', 'HONORARIOS', 'TIEMPO', 'VALOR TOTAL', 'REFERENCIAS',
        ], $export->headings());
        $this->assertCount(12, $row);
        $this->assertSame('AIM', $row['dependencia']);
        $this->assertSame('PERSONA PRUEBA', $row['nombre']);
        $this->assertSame('0012345', $row['cedula']);
        $this->assertSame('24/08/2026', ExcelDate::excelToDateTimeObject($row['finalizacion'])->format('d/m/Y'));
        $this->assertSame(216, $row['tiempo']);
        $this->assertSame(51038666.67, $row['valor_total']);
        $this->assertSame('Referencia A, Referencia B', $row['referencias']);
        $this->assertSame('dd/mm/yyyy', $export->columnFormats()['H']);
        $this->assertSame('$#,##0.00', $export->columnFormats()['K']);

        $path = tempnam(sys_get_temp_dir(), 'seguimiento-export-');
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('DEPENDENCIA', $sheet->getCell('A1')->getValue());
            $this->assertSame('0012345', $sheet->getCell('C2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
            $this->assertSame('dd/mm/yyyy', $sheet->getStyle('H2')->getNumberFormat()->getFormatCode());
            $this->assertSame('$#,##0.00', $sheet->getStyle('K2')->getNumberFormat()->getFormatCode());
            $this->assertSame('A1:L1', $sheet->getAutoFilter()->getRange());
            $this->assertSame('A2', $sheet->getFreezePane());
        } finally {
            @unlink($path);
        }
    }
}
