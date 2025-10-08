<?php

namespace App\Imports;

use App\Models\Seguimiento;
use App\Models\Persona;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Alert;
use Maatwebsite\Excel\Validators\Failure;

class SeguimientoImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure 
{
    use Importable, SkipsFailures;
    
    // Propiedad pública que el controlador lee para obtener los fallos (validación + lógica)
    public $logicFailures = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) 
        {
            // El índice de fila que el usuario ve es $index + 2 (Encabezado + 1)
            $rowNumber = $index + 2; 
            $cedula = (string)($row['cedula_o_nit'] ?? null);
            $cedula = trim($cedula); // Limpiar espacios

            // *** AJUSTE CLAVE: IGNORAR FILAS VACÍAS O SIN CÉDULA/NIT (Problema 2 resuelto) ***
            if (empty($cedula)) {
                // Si está completamente vacía, la ignoramos sin reportar como error.
                // Si se proporcionó alguna otra columna pero no la cédula, la reportamos como fallo.
                $isRowEmpty = count(array_filter($row->toArray())) === 0;
                
                if (!$isRowEmpty) {
                    $this->logicFailures[] = [
                        'row' => $rowNumber,
                        'cedula' => 'N/A',
                        'errors' => ["No se proporcionó 'cedula_o_nit'. Esta fila será omitida. (Fallo de Lógica)"],
                    ];
                }
                continue;
            }
            // *** FIN AJUSTE CLAVE ***

            // Fallo de Lógica 2: Persona no encontrada
            $persona = Persona::where('cedula_o_nit', $cedula)->first();

            if (!$persona) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => ["Persona no encontrada en la base de datos. (Fallo de Lógica)"],
                ];
                continue;
            }

            try {
                // 1. Prepara los datos del Seguimiento
                $dataToCreate = [
                    'persona_id'            => $persona->id, 
                    'tipo'                  => $row['tipo'] ?? null,
                    'observaciones'         => $row['observaciones'] ?? null,

                    // --- Claves Foráneas ---
                    'evaluacion_id'         => intval($row['evaluacion_id'] ?? 0) ?: null,
                    'nivel_academico_id'    => intval($row['nivel_academico_id'] ?? 0) ?: null,
                    'estado_id'             => intval($row['estado_id'] ?? 0) ?: null,
                    'estado_contrato_id'    => intval($row['estado_contrato_id'] ?? 0) ?: null,
                    'secretaria_id'         => intval($row['secretaria_id'] ?? 0) ?: null,
                    'gerencia_id'           => intval($row['gerencia_id'] ?? 0) ?: null,
                    'fuente_id'             => intval($row['fuente_id'] ?? 0) ?: null,

                    // --- Fechas ---
                    'fecha_entrevista'          => $this->cleanDate($row['fecha_entrevista'] ?? null),
                    'fecha_acta_inicio'         => $this->cleanDate($row['fecha_acta_inicio'] ?? null),
                    'fecha_finalizacion'        => $this->cleanDate($row['fecha_finalizacion'] ?? null),
                    'fecha_aut_despacho'        => $this->cleanDate($row['fecha_aut_despacho'] ?? null), // Se lee el campo de fecha directamente
                    'fecha_aut_planeacion'      => $this->cleanDate($row['fecha_aut_planeacion'] ?? null),
                    'fecha_aut_administrativa'  => $this->cleanDate($row['fecha_aut_administrativa'] ?? null),
                    'fecha_acta_inicio_adicion' => $this->cleanDate($row['fecha_acta_inicio_adicion'] ?? null),
                    'fecha_finalizacion_adicion'=> $this->cleanDate($row['fecha_finalizacion_adicion'] ?? null),

                    // --- Otros Campos ---
                    'anio'                      => $row['anio'] ?? null,
                    'numero_contrato'           => $row['numero_contrato'] ?? null,
                    'tiempo_ejecucion_dias'     => intval($row['tiempo_ejecucion_dias'] ?? 0) ?: null,
                    'valor_mensual'             => $row['valor_mensual'] ?? null,
                    'valor_total'               => $row['valor_total'] ?? null,
                    // Se lee el campo SI/NO o 1/0
                    'aut_despacho'              => $this->cleanBool($row['aut_despacho'] ?? null), 
                    'aut_planeacion'            => $this->cleanBool($row['aut_planeacion'] ?? null),
                    'aut_administrativa'        => $this->cleanBool($row['aut_administrativa'] ?? null),
                    'adicion'                   => $row['adicion'] ?? 'NO', // Default 'NO'
                    'tiempo_ejecucion_dias_adicion' => intval($row['tiempo_ejecucion_dias_adicion'] ?? 0) ?: null,
                    'tiempo_total_ejecucion_dias'   => intval($row['tiempo_total_ejecucion_dias'] ?? 0) ?: null,
                    'valor_adicion'             => $row['valor_adicion'] ?? null,
                    'valor_total_contrato'      => $row['valor_total_contrato'] ?? null,
                    'observaciones_contrato'    => $row['observaciones_contrato'] ?? null,
                    'continua'                  => $row['continua'] ?? null,
                ];

                // 2. Crear el Seguimiento
                Seguimiento::create($dataToCreate); 

            } catch (\Throwable $e) {
                // Fallo de Lógica 3: Error de DB/Throwable
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => ["Error interno al crear el seguimiento: " . $e->getMessage()],
                ];
                Log::error("Error de importación de Seguimiento para Cédula {$cedula}. Mensaje: " . $e->getMessage());
            }
        }
    }
    
    private function cleanDate($value)
    {
        if (is_numeric($value) && $value > 25569) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        return is_string($value) ? date('Y-m-d', strtotime($value)) : null;
    }

    private function cleanBool($value)
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['1', 'SI', 'TRUE', 'Y', 'YES']) ? 1 : 0;
    }
    
    public function rules(): array
    {
        return [
            // Se asume que el campo en el Excel es 'cedula_o_nit'
            'cedula_o_nit' => ['required', 'max:20'], 
            'tipo'     => ['nullable', 'in:entrevista,contrato'], 
            'adicion'  => ['nullable', 'in:SI,NO'],
            'continua' => ['nullable', 'in:SI,NO'],
            'evaluacion_id'      => ['nullable', 'integer', 'exists:evaluaciones,id'],
            'nivel_academico_id' => ['nullable', 'integer', 'exists:niveles_academicos,id'],
            'estado_id'          => ['nullable', 'integer', 'exists:estados,id'],
            'estado_contrato_id' => ['nullable', 'integer', 'exists:estados,id'],
            'secretaria_id'      => ['nullable', 'integer', 'exists:secretarias,id'],
            'gerencia_id'        => ['nullable', 'integer', 'exists:gerencias,id'],
            'fuente_id'          => ['nullable', 'integer', 'exists:fuentes,id'],
            'fecha_entrevista'      => ['nullable', 'date'],
            'fecha_aut_despacho'    => ['nullable', 'date'], // Se valida como fecha
            'fecha_aut_planeacion'  => ['nullable', 'date'],
            'fecha_aut_administrativa' => ['nullable', 'date'],
            'valor_mensual'         => ['nullable', 'numeric'],
            'valor_total'           => ['nullable', 'numeric'],
            'valor_adicion'         => ['nullable', 'numeric'],
            'valor_total_contrato'  => ['nullable', 'numeric'],
        ];
    }
    
    /**
     * Captura los errores de VALIDACIÓN de Maatwebsite\Excel y los almacena en logicFailures
     */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $rowNumber = $failure->row();
            // Intentamos obtener la cédula/nit para referencia
            $cedula = $failure->values()['cedula_o_nit'] ?? 'N/A';
            
            $this->logicFailures[] = [
                'row' => $rowNumber,
                'cedula' => $cedula,
                'errors' => $failure->errors(), // Esto son los mensajes de error de Laravel
            ];
            
            Log::warning("Fila {$rowNumber} saltada por validación de Seguimiento. Cédula: {$cedula}.");
        }
    }
}
