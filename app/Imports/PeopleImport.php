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
use Alert; // Si usas Backpack o un paquete de alertas

class PeopleImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure 
{
    use Importable, SkipsFailures;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            // Usamos try/catch por fila para capturar errores de BD (claves foráneas)
            try {
                // 1. Prepara los datos (Sanitizando la cédula y los IDs)
                $dataToCreate = [
                    'nombre_contratista'          => $row['nombre_contratista'] ?? null,
                    // 🚨 CRÍTICO: Forzar a string para el almacenamiento, aunque la validación lo lea como número.
                    'cedula_o_nit'                => (string)($row['cedula_o_nit'] ?? ''), 
                    'celular'                     => $row['celular'] ?? null,
                    'genero'                      => $row['genero'] ?? null,
                    'tecnico_tecnologo_profesion' => $row['tecnico_tecnologo_profesion'] ?? null,
                    'especializacion'             => $row['especializacion'] ?? null,
                    'maestria'                    => $row['maestria'] ?? null,
                    'no_tocar'                    => $row['no_tocar'] ?? false,
                    
                    // Claves Foráneas: Aseguramos que son números o null
                    'estado_persona_id'           => intval($row['estado_persona_id'] ?? 0) ?: null,
                    'tipos_id'                    => intval($row['tipos_id'] ?? 0) ?: null,
                    'nivel_academico_id'          => intval($row['nivel_academico_id'] ?? 0) ?: null,
                    'caso_id'                     => intval($row['caso_id'] ?? 0) ?: null,
                    'secretaria_id'               => intval($row['secretaria_id'] ?? 0) ?: null,
                    'gerencia_id'                 => intval($row['gerencia_id'] ?? 0) ?: null,
                ];

                // 2. CREAR el modelo Persona
                $person = Persona::create($dataToCreate); 

                // 3. 🚨 LÓGICA DE REFERENCIAS (Muchos a Muchos)
                if (isset($row['referencias']) && !empty($row['referencias'])) {
                    $referenceIds = array_filter(array_map('trim', explode(',', $row['referencias'])));
                    
                    if (!empty($referenceIds)) {
                        // Sincroniza la tabla pivote persona_referencia
                        $person->referencias()->sync($referenceIds); 
                    }
                }
            } catch (\Throwable $e) {
                // Registrar cualquier error de BD (Clave Foránea, etc.)
                Log::error("Error de BD/Asignación en Cédula " . ($row['cedula_o_nit'] ?? 'N/A') . ". Mensaje: " . $e->getMessage());
            }
        }
    }
    
    // --- LÓGICA DE VALIDACIÓN ---

    public function rules(): array
    {
        return [
            // 🚨 SOLUCIÓN CÉDULA: Quitamos 'string' para evitar el fallo de tipo de dato, mantenemos 'unique'
            'cedula_o_nit' => ['required', 'max:20', 'unique:personas,cedula_o_nit'],
            
            // Regla 'in' para el ENUM 'genero'
            'genero'       => ['nullable', 'in:Masculino,Femenino'], 
            
            // Reglas 'exists' (Ajustadas para usar nombres de tablas sin tildes/caracteres especiales)
            'estado_persona_id'  => ['nullable', 'integer', 'exists:estado_personas,id'],
            'tipos_id'           => ['nullable', 'integer', 'exists:tipos,id'],
            // Usamos niveles_academicos (sin tilde) asumiendo que corregiste el nombre en la BD
            'nivel_academico_id' => ['nullable', 'integer', 'exists:niveles_academicos,id'], 
            'caso_id'            => ['nullable', 'integer', 'exists:casos,id'],
            'secretaria_id'      => ['nullable', 'integer', 'exists:secretarias,id'],
            'gerencia_id'        => ['nullable', 'integer', 'exists:gerencias,id'],
            // Nota: No necesitas validar 'referencias' aquí, ya que 'sync' lo maneja al fallar silenciosamente.
        ];
    }
    
    // Método para manejar los fallos de validación (como la cédula duplicada)
    public function onFailure(\Maatwebsite\Excel\Validators\Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $row = $failure->row();
            $errors = $failure->errors();
            
            // Registramos el fallo de validación
            Log::warning("Fila $row saltada por validación. Errores: " . json_encode($errors));

            // Informamos al usuario en la interfaz
            Alert::warning("Fila $row no importada: " . implode(', ', $errors))->flash();
        }
    }
}