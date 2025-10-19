<?php

namespace App\Imports;

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

class PeopleImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure 
{
    use Importable, SkipsFailures;

    public $logicFailures = []; // 👈 para capturar errores

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) 
        {
            $rowNumber = $index + 2;
            $cedula = trim((string)($row['cedula_o_nit'] ?? ''));

            if (empty($cedula)) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => 'N/A',
                    'errors' => ["No se proporcionó 'cedula_o_nit'."],
                ];
                continue;
            }

            try {

                $dataToCreate = [
                    'nombre_contratista'          => $row['nombre_contratista'] ?? null,
                    'celular'                     => $row['celular'] ?? null,
                    'genero'                      => $row['genero'] ?? null,
                    'tecnico_tecnologo_profesion' => $row['tecnico_tecnologo_profesion'] ?? null,
                    'especializacion'             => $row['especializacion'] ?? null,
                    'maestria'                    => $row['maestria'] ?? null,
                    'no_tocar'                    => $row['no_tocar'] ?? false,
                    'referencia_2'                => $row['referencia_2'] ?? null,

                    'estado_persona_id'           => intval($row['estado_persona_id'] ?? 0) ?: null,
                    'tipos_id'                    => intval($row['tipos_id'] ?? 0) ?: null,
                    'nivel_academico_id'          => intval($row['nivel_academico_id'] ?? 0) ?: null,
                    'caso_id'                     => intval($row['caso_id'] ?? 0) ?: null,
                    'secretaria_id'               => intval($row['secretaria_id'] ?? 0) ?: null,
                    'gerencia_id'                 => intval($row['gerencia_id'] ?? 0) ?: null,
                ];

                // ✅ updateOrCreate basado en cédula para permitir ACTUALIZAR si ya existe
                $person = Persona::updateOrCreate(
                    ['cedula_o_nit' => $cedula],
                    $dataToCreate
                );

                // ✅ Relación con referencias
                if (isset($row['referencia_id']) && !empty($row['referencia_id'])) {
                    $referenceIds = array_filter(array_map('trim', explode(',', $row['referencia_id'])));
                    if (!empty($referenceIds)) {
                        $person->referencias()->sync($referenceIds);
                    }
                }

            } catch (\Throwable $e) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => ["Error al crear/actualizar: " . $e->getMessage()],
                ];
            }
        }
    }

    public function rules(): array
    {
        return [
            'cedula_o_nit'      => ['required', 'max:20'], // ❌ Quitamos 'unique' para permitir update
            'genero'            => ['nullable', 'in:Masculino,Femenino'], 
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
