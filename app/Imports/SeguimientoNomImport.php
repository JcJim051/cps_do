<?php

namespace App\Imports;

use App\Models\SeguimientoNom;
use App\Models\Persona;
use App\Models\Cargo;
use App\Models\TipoVinculacion;
use App\Models\Secretaria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;

class SeguimientoNomImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public array $logicFailures = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $cedula = trim((string) ($row['cedula_o_nit'] ?? ''));

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
                $secretariaId = (int) ($row['dependencia_id'] ?? 0);
                $cargoId = (int) ($row['cargo_id'] ?? 0);
                $tipoVincId = (int) ($row['tipo_vinculacion_id'] ?? 0);
                $fechaIngreso = $row['fecha_ingreso'] ?? null;
                $salario = $row['salario'] ?? null;

                if (!$secretariaId || !Secretaria::where('id', $secretariaId)->exists()) {
                    throw new \Exception('Dependencia inválida');
                }
                if (!$cargoId || !Cargo::where('id', $cargoId)->exists()) {
                    throw new \Exception('Cargo inválido');
                }
                if (!$tipoVincId || !TipoVinculacion::where('id', $tipoVincId)->exists()) {
                    throw new \Exception('Tipo de vinculación inválido');
                }
                if (empty($fechaIngreso)) {
                    throw new \Exception('Fecha de ingreso requerida');
                }
                if ($salario === null || $salario === '') {
                    throw new \Exception('Salario requerido');
                }

                $data = [
                    'persona_id' => $persona->id,
                    'secretaria_id' => $secretariaId,
                    'cargo_id' => $cargoId,
                    'tipo_vinculacion_id' => $tipoVincId,
                    'fecha_ingreso' => $fechaIngreso,
                    'salario' => $salario,
                ];

                if (!empty($row['id'])) {
                    $seguimiento = SeguimientoNom::find($row['id']);
                    if (!$seguimiento) {
                        throw new \Exception("Seguimiento ID {$row['id']} no existe");
                    }
                    $seguimiento->update($data);
                } else {
                    SeguimientoNom::create($data);
                }

            } catch (\Throwable $e) {
                $this->logicFailures[] = [
                    'row' => $rowNumber,
                    'cedula' => $cedula,
                    'errors' => [$e->getMessage()],
                ];
                Log::error("Error import SeguimientoNom | Fila {$rowNumber} | {$cedula} | {$e->getMessage()}");
            }
        }
    }

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
