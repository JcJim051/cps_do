<?php

namespace App\Imports;

use App\Models\Seguimiento;
use App\Models\Persona;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;

class SeguimientoImport implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure
{
    use Importable, SkipsFailures;

    public array $logicFailures = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {

            $rowNumber = $index + 2;
            $cedula = trim((string)($row['cedula_o_nit'] ?? ''));

            /* ===============================
             | Fila vacía
             =============================== */
            if ($cedula === '') {
                if (count(array_filter($row->toArray())) > 0) {
                    $this->logicFailures[] = [
                        'row' => $rowNumber,
                        'cedula' => 'N/A',
                        'errors' => ['No se proporcionó cedula_o_nit'],
                    ];
                }
                continue;
            }

            /* ===============================
             | Persona
             =============================== */
            $persona = Persona::where('cedula_o_nit', $cedula)->first();

            if (!$persona) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => ['Persona no encontrada'],
                ];
                continue;
            }

            try {

                $editableData = $this->mapEditableData($row);

                /* ===============================
                 | UPDATE
                 =============================== */
                if (!empty($row['id'])) {

                    $seguimiento = Seguimiento::find($row['id']);

                    if (!$seguimiento) {
                        throw new \Exception("Seguimiento ID {$row['id']} no existe");
                    }

                    if ((int)$seguimiento->persona_id !== (int)$persona->id) {
                        throw new \Exception("El seguimiento no pertenece a la persona");
                    }

                    // 🚫 NO recalcular (Excel manda)
                    $seguimiento->skipAutoCalculation = true;
                    $seguimiento->fill($editableData);

                    unset($seguimiento->skipAutoCalculation);
                    unset($seguimiento['skipAutoCalculation']);
                    $seguimiento->save();
                }
                /* ===============================
                 | CREATE
                 =============================== */
                else {

                    if (empty($row['anio'])) {
                        throw new \Exception("El año es obligatorio para crear un seguimiento");
                    }

                    $seguimiento = new Seguimiento(array_merge($editableData, [
                        'persona_id' => $persona->id,
                        'anio'       => $row['anio'],
                        'tipo'       => $row['tipo'] ?? 'contrato',
                    ]));

                    // 🚫 NO recalcular (Excel manda)
                    $seguimiento->skipAutoCalculation = true;

                    unset($seguimiento->skipAutoCalculation);   
                    unset($seguimiento['skipAutoCalculation']);
                    $seguimiento->save();
                    
                }

            } catch (\Throwable $e) {

                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => [$e->getMessage()],
                ];

                Log::error(
                    "Error import Seguimiento | Fila {$rowNumber} | {$cedula} | {$e->getMessage()}"
                );
            }
        }
    }

    /* =====================================================
     | MAPEO EXACTO DEL EXCEL (SIN CÁLCULOS)
     ===================================================== */
    protected function mapEditableData($row): array
    {
        return [
            'tipo'        => $row['tipo'] ?? null,
            'observaciones' => $row['observaciones'] ?? null,

            'evaluacion_id'      => $this->num($row['evaluacion_id'] ?? null),
            'nivel_academico_id' => $this->num($row['nivel_academico_id'] ?? null),
            'estado_id'          => $this->num($row['estado_id'] ?? null),
            'estado_contrato_id' => $this->num($row['estado_contrato_id'] ?? null),
            'secretaria_id'      => $this->num($row['secretaria_id'] ?? null),
            'gerencia_id'        => $this->num($row['gerencia_id'] ?? null),
            'fuente_id'          => $this->num($row['fuente_id'] ?? null),

            'fecha_entrevista'   => $this->cleanDate($row['fecha_entrevista'] ?? null),
            'fecha_acta_inicio'  => $this->cleanDate($row['fecha_acta_inicio'] ?? null),
            'fecha_finalizacion' => $this->cleanDate($row['fecha_finalizacion'] ?? null),

            'fecha_aut_despacho'       => $this->cleanDate($row['fecha_aut_despacho'] ?? null),
            'fecha_aut_planeacion'     => $this->cleanDate($row['fecha_aut_planeacion'] ?? null),
            'fecha_aut_administrativa' => $this->cleanDate($row['fecha_aut_administrativa'] ?? null),

            'fecha_acta_inicio_adicion'  => $this->cleanDate($row['fecha_acta_inicio_adicion'] ?? null),
            'fecha_finalizacion_adicion' => $this->cleanDate($row['fecha_finalizacion_adicion'] ?? null),

            'numero_contrato' => $row['numero_contrato'] ?? null,

            // 🔴 VALORES DIRECTOS DEL EXCEL
            'tiempo_ejecucion_dias'          => $this->num($row['tiempo_ejecucion_dias'] ?? null),
            'tiempo_ejecucion_dias_adicion'  => $this->num($row['tiempo_ejecucion_dias_adicion'] ?? null),
            'tiempo_total_ejecucion_dias'    => $this->num($row['tiempo_total_ejecucion_dias'] ?? null),

            'valor_mensual'         => $this->num($row['valor_mensual'] ?? null),
            'valor_total'           => $this->num($row['valor_total'] ?? null),
            'valor_adicion'         => $this->num($row['valor_adicion'] ?? null),
            'valor_total_contrato'  => $this->num($row['valor_total_contrato'] ?? null),

            'aut_despacho'       => $this->cleanBool($row['aut_despacho'] ?? null),
            'aut_planeacion'     => $this->cleanBool($row['aut_planeacion'] ?? null),
            'aut_administrativa' => $this->cleanBool($row['aut_administrativa'] ?? null),

            'adicion'                => $row['adicion'] ?? null,
            'observaciones_contrato' => $row['observaciones_contrato'] ?? null,
            'continua'               => $row['continua'] ?? null,
        ];
    }

    /* =====================================================
     | HELPERS
     ===================================================== */
    private function num($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? $value : null;
    }

    private function cleanDate($value)
    {
        if (is_numeric($value) && $value > 25569) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                ->format('Y-m-d');
        }

        return is_string($value) && strtotime($value)
            ? date('Y-m-d', strtotime($value))
            : null;
    }

    private function cleanBool($value)
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, ['1', 'SI', 'TRUE', 'Y', 'YES']) ? 1 : 0;
    }

    /* =====================================================
     | VALIDACIONES
     ===================================================== */
    public function rules(): array
    {
        return [
            'cedula_o_nit' => ['required', 'max:20'],
        ];
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->logicFailures[] = [
                'row' => $failure->row(),
                'cedula' => $failure->values()['cedula_o_nit'] ?? 'N/A',
                'errors' => $failure->errors(),
            ];
        }
    }
}
