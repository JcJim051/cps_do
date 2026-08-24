<?php

namespace App\Imports;

use App\Http\Controllers\Admin\PrevalidacionFuenteController;
use App\Models\PrevalidacionFuente;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PrevalidacionFuentesImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];
    public int $processed = 0;

    public function __construct(private GoogleSheetsService $sheets)
    {
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                if (blank($row['nombre'] ?? null) && blank($row['spreadsheet_url_o_id'] ?? null)) {
                    continue;
                }
                foreach (['nombre', 'secretaria_id', 'nit_entidad', 'spreadsheet_url_o_id', 'hoja', 'columna_cedula', 'columna_nombre', 'columna_estado'] as $field) {
                    if (blank($row[$field] ?? null)) {
                        throw new \RuntimeException("Falta {$field}");
                    }
                }
                $rawId = trim((string) $row['spreadsheet_url_o_id']);
                $map = [];
                foreach (PrevalidacionFuenteController::MAPPING_FIELDS as $canonical => $excelField) {
                    if (filled($row[$excelField] ?? null)) {
                        $map[$canonical] = trim((string) $row[$excelField]);
                    }
                }
                PrevalidacionFuente::updateOrCreate([
                    'spreadsheet_id' => $this->sheets->spreadsheetId($rawId),
                    'hoja' => trim((string) $row['hoja']),
                ], [
                    'nombre' => trim((string) $row['nombre']),
                    'secretaria_id' => (int) $row['secretaria_id'],
                    'nit_entidad' => preg_replace('/\D+/', '', (string) $row['nit_entidad']),
                    'spreadsheet_url' => str_starts_with($rawId, 'http') ? $rawId : null,
                    'fila_encabezados' => (int) ($row['fila_encabezados'] ?: 1),
                    'mapeo_columnas' => $map,
                    'columna_clave' => filled($row['columna_clave'] ?? null) ? trim((string) $row['columna_clave']) : null,
                    'columna_estado' => trim((string) $row['columna_estado']),
                    'columna_editado' => trim((string) ($row['columna_editado'] ?: 'EDITADO_EN_INTEGRA')),
                    'activa' => !in_array(mb_strtoupper(trim((string) ($row['activa'] ?? 'SI'))), ['NO', '0'], true),
                ]);
                $this->processed++;
            } catch (\Throwable $e) {
                $this->errors[] = 'Fila '.($index + 2).': '.$e->getMessage();
            }
        }
    }
}
