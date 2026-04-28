<?php

namespace App\Imports;

use App\Models\Programa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Validators\Failure;

class ProgramaImport implements ToCollection, WithHeadingRow, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public array $logicFailures = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $cedula = trim((string) ($row['cedula'] ?? ''));

            if ($cedula === '') {
                if (count(array_filter($row->toArray())) > 0) {
                    $this->logicFailures[] = [
                        'row' => $rowNumber,
                        'cedula' => 'N/A',
                        'errors' => ['No se proporcionó cédula.'],
                    ];
                }
                continue;
            }

            try {
                Programa::updateOrCreate(
                    ['cedula' => $cedula],
                    [
                        'operador' => $this->clean($row['operador'] ?? null),
                        'municipio' => $this->clean($row['municipio'] ?? null),
                        'institucion' => $this->clean($row['institucion'] ?? null),
                        'sede' => $this->clean($row['sede'] ?? null),
                        'nombre_apellidos' => $this->clean($row['nombre_apellidos'] ?? null),
                        'contacto' => $this->clean($row['contacto'] ?? null),
                        'ref_1' => $this->clean($row['ref_1'] ?? null),
                        'ref_2' => $this->clean($row['ref_2'] ?? null),
                    ]
                );
            } catch (\Throwable $e) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => [$e->getMessage()],
                ];

                Log::error("Error import Programa | Fila {$rowNumber} | {$cedula} | {$e->getMessage()}");
            }
        }
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->logicFailures[] = [
                'row' => $failure->row(),
                'cedula' => $failure->values()['cedula'] ?? 'N/A',
                'errors' => $failure->errors(),
            ];
        }
    }
}

