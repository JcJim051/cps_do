<?php

namespace App\Imports;

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

class PeopleImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure 
{
    use Importable, SkipsFailures;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            try {
                $dataToCreate = [
                    'nombre_contratista'          => $row['nombre_contratista'] ?? null,
                    'cedula_o_nit'                => (string)($row['cedula_o_nit'] ?? ''), 
                    'celular'                     => $row['celular'] ?? null,
                    'genero'                      => $row['genero'] ?? null,
                    'tecnico_tecnologo_profesion' => $row['tecnico_tecnologo_profesion'] ?? null,
                    'especializacion'             => $row['especializacion'] ?? null,
                    'maestria'                    => $row['maestria'] ?? null,
                    'no_tocar'                    => $row['no_tocar'] ?? false,
                    'referencia_2'                => $row['referencia_2'] ?? null, // ✅ Nuevo campo

                    'estado_persona_id'           => intval($row['estado_persona_id'] ?? 0) ?: null,
                    'tipos_id'                    => intval($row['tipos_id'] ?? 0) ?: null,
                    'nivel_academico_id'          => intval($row['nivel_academico_id'] ?? 0) ?: null,
                    'caso_id'                     => intval($row['caso_id'] ?? 0) ?: null,
                    'secretaria_id'               => intval($row['secretaria_id'] ?? 0) ?: null,
                    'gerencia_id'                 => intval($row['gerencia_id'] ?? 0) ?: null,
                ];

                // Crear la persona
                $person = Persona::create($dataToCreate); 

                // Sincronizar referencias en tabla pivote
                if (isset($row['referencia_id']) && !empty($row['referencia_id'])) {
                    $referenceIds = array_filter(array_map('trim', explode(',', $row['referencia_id'])));
                    if (!empty($referenceIds)) {
                        $person->referencias()->sync($referenceIds); 
                    }
                }

            } catch (\Throwable $e) {
                Log::error("Error de BD/Asignación en Cédula " . ($row['cedula_o_nit'] ?? 'N/A') . ". Mensaje: " . $e->getMessage());
            }
        }
    }
    
    public function rules(): array
    {
        return [
            'cedula_o_nit'      => ['required', 'max:20', 'unique:personas,cedula_o_nit'],
            'genero'            => ['nullable', 'in:Masculino,Femenino'], 
            'estado_persona_id' => ['nullable', 'integer', 'exists:estado_personas,id'],
            'tipos_id'          => ['nullable', 'integer', 'exists:tipos,id'],
            'nivel_academico_id' => ['nullable', 'integer', 'exists:niveles_academicos,id'], 
            'caso_id'           => ['nullable', 'integer', 'exists:casos,id'],
            'secretaria_id'     => ['nullable', 'integer', 'exists:secretarias,id'],
            'gerencia_id'       => ['nullable', 'integer', 'exists:gerencias,id'],
            'referencia_2'      => ['nullable', 'string', 'max:255'], // Validación para el nuevo campo
            'referencia_id'     => ['nullable'], // No se valida existencia, se maneja con sync
        ];
    }
    
    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $row = $failure->row();
            $errors = $failure->errors();
            Log::warning("Fila $row saltada por validación. Errores: " . json_encode($errors));
            Alert::warning("Fila $row no importada: " . implode(', ', $errors))->flash();
        }
    }
}
