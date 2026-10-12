<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\ProgramaFuente;
use App\Support\ProgramaMunicipios;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgramasGoogleSyncService
{
    private const REQUIRED_HEADERS = [
        'municipio', 'operador', 'perfil', 'institucion', 'nombre', 'cedula',
        'contacto', 'referencia_1', 'referencia_2', 'estado',
    ];

    private const HEADER_ALIASES = [
        'MUNICIPIO' => 'municipio',
        'OPERADOR' => 'operador',
        'PERFIL' => 'perfil',
        'INSTITUCION_EDUCATIVA_Y_O_SEDE' => 'institucion',
        'INSTITUCION' => 'institucion',
        'NOMBRE_COMPLETO' => 'nombre',
        'NOMBRE_Y_APELLIDOS' => 'nombre',
        'CEDULA' => 'cedula',
        'CONTACTO' => 'contacto',
        'REFERENCIA_1' => 'referencia_1',
        'REF_1' => 'referencia_1',
        'REFERENCIA_2' => 'referencia_2',
        'REF_2' => 'referencia_2',
        'ESTADO' => 'estado',
        'ID_INTEGRA' => 'external_id',
    ];

    public function __construct(private GoogleSheetsService $sheets)
    {
    }

    public function sincronizar(ProgramaFuente $fuente, bool $dryRun = false): array
    {
        $summary = [
            'filas_leidas' => 0,
            'validas' => 0,
            'creados' => 0,
            'actualizados' => 0,
            'inactivados' => 0,
            'ids_generados' => 0,
            'omitidos' => 0,
            'errores' => [],
            'dry_run' => $dryRun,
        ];

        try {
            $headerRow = (int) ($fuente->fila_encabezados ?: 1);
            $rows = $this->sheets->values($fuente->spreadsheet_id, $fuente->hoja, $headerRow);
            if ($rows === []) {
                throw new \RuntimeException('La hoja no contiene encabezados.');
            }

            $headers = array_shift($rows);
            $indexes = $this->headerIndexes($headers);
            $missing = collect(self::REQUIRED_HEADERS)->reject(fn (string $field) => isset($indexes[$field]))->values();
            if ($missing->isNotEmpty()) {
                throw new \RuntimeException('Faltan columnas obligatorias: '.$missing->implode(', ').'.');
            }

            $idIndex = $indexes['external_id'] ?? count($headers);
            if (!isset($indexes['external_id']) && !$dryRun) {
                $this->sheets->updateCell($fuente->spreadsheet_id, $fuente->hoja, $headerRow, $idIndex, 'ID_INTEGRA');
            }

            $prepared = [];
            $seen = [];
            $idUpdates = [];

            foreach ($rows as $offset => $row) {
                $rowNumber = $headerRow + $offset + 1;
                $summary['filas_leidas']++;
                $source = [];
                foreach ($indexes as $field => $index) {
                    $source[$field] = $row[$index] ?? null;
                }

                if ($this->emptyRow($source)) {
                    $summary['omitidos']++;
                    continue;
                }

                $operador = $this->clean($source['operador'] ?? null);
                $nombre = $this->clean($source['nombre'] ?? null);
                $municipioRaw = $this->clean($source['municipio'] ?? null);
                $municipio = ProgramaMunicipios::canonical($municipioRaw);
                $missingValues = [];
                if ($operador === null) $missingValues[] = 'OPERADOR';
                if ($nombre === null) $missingValues[] = 'NOMBRE COMPLETO';
                if ($municipioRaw === null) $missingValues[] = 'MUNICIPIO';

                if ($missingValues !== []) {
                    $summary['errores'][] = "Fila {$rowNumber}: faltan ".implode(', ', $missingValues).'.';
                    $summary['omitidos']++;
                    continue;
                }
                if ($municipio === null) {
                    $summary['errores'][] = "Fila {$rowNumber}: municipio fuera del catálogo ({$municipioRaw}).";
                    $summary['omitidos']++;
                    continue;
                }

                $externalId = $this->clean($source['external_id'] ?? ($row[$idIndex] ?? null));
                if ($externalId === null) {
                    $externalId = $dryRun ? "PENDIENTE-FILA-{$rowNumber}" : 'PRG-'.Str::upper((string) Str::ulid());
                    $summary['ids_generados']++;
                    if (!$dryRun) {
                        $idUpdates[] = [
                            'sheet' => $fuente->hoja,
                            'row' => $rowNumber,
                            'column' => $idIndex,
                            'value' => $externalId,
                        ];
                    }
                }

                if (isset($seen[$externalId])) {
                    $summary['errores'][] = "Filas {$seen[$externalId]} y {$rowNumber}: ID_INTEGRA duplicado {$externalId}.";
                    $summary['omitidos']++;
                    continue;
                }
                $seen[$externalId] = $rowNumber;

                $attributes = [
                    'operador' => $operador,
                    'perfil' => $this->clean($source['perfil'] ?? null),
                    'municipio' => $municipio,
                    'institucion' => $this->clean($source['institucion'] ?? null),
                    'sede' => null,
                    'nombre_apellidos' => $nombre,
                    'cedula' => $this->document($source['cedula'] ?? null),
                    'contacto' => $this->clean($source['contacto'] ?? null),
                    'ref_1' => $this->clean($source['referencia_1'] ?? null),
                    'ref_2' => $this->clean($source['referencia_2'] ?? null),
                    'estado' => $this->clean($source['estado'] ?? null),
                    'fila_origen' => $rowNumber,
                    'origen' => 'google_sheets',
                    'presente_en_origen' => true,
                    'hash_origen' => hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    'ultima_sincronizacion_at' => now(),
                ];

                $prepared[] = compact('externalId', 'attributes');
                $summary['validas']++;
            }

            if ($dryRun) {
                return $summary;
            }

            if ($idUpdates !== []) {
                $this->sheets->updateCells($fuente->spreadsheet_id, $idUpdates);
            }
            $this->sheets->protectColumnsWithWarning($fuente->spreadsheet_id, $fuente->hoja, [
                'ID_INTEGRA' => $idIndex,
            ]);

            DB::transaction(function () use ($prepared, &$summary) {
                $incomingIds = collect($prepared)->pluck('externalId')->all();
                $summary['inactivados'] = Programa::query()
                    ->where('origen', 'google_sheets')
                    ->where('presente_en_origen', true)
                    ->when($incomingIds !== [], fn ($query) => $query->whereNotIn('external_id', $incomingIds))
                    ->count();

                Programa::query()->where('origen', 'google_sheets')->update(['presente_en_origen' => false]);

                foreach ($prepared as $item) {
                    $record = Programa::query()->where('external_id', $item['externalId'])->first();
                    if (!$record) {
                        $record = $this->legacyMatch($item['attributes']);
                    }

                    $isNew = !$record;
                    $record ??= new Programa();
                    $record->external_id = $item['externalId'];
                    $record->fill($item['attributes']);
                    $record->save();
                    $summary[$isNew ? 'creados' : 'actualizados']++;
                }
            });

            $fuente->update([
                'ultima_sincronizacion_at' => now(),
                'ultimo_resumen' => collect($summary)->except('errores')->merge([
                    'errores' => count($summary['errores']),
                ])->all(),
                'ultimo_error' => $summary['errores'] === []
                    ? null
                    : implode("\n", array_slice($summary['errores'], 0, 100)),
            ]);

            return $summary;
        } catch (\Throwable $e) {
            if (!$dryRun) {
                $fuente->update(['ultimo_error' => $e->getMessage()]);
            }
            throw $e;
        }
    }

    private function headerIndexes(array $headers): array
    {
        $indexes = [];
        foreach ($headers as $index => $header) {
            $key = preg_replace('/[^A-Z0-9]+/', '_', ProgramaMunicipios::normalize($header));
            $key = trim((string) $key, '_');
            if (isset(self::HEADER_ALIASES[$key])) {
                $indexes[self::HEADER_ALIASES[$key]] = $index;
            }
        }

        return $indexes;
    }

    private function legacyMatch(array $attributes): ?Programa
    {
        $query = Programa::query()->whereNull('external_id');
        if ($attributes['cedula']) {
            $query->where('cedula', $attributes['cedula']);
        } else {
            $query->where('nombre_apellidos', $attributes['nombre_apellidos']);
        }

        return $query->get()->first(function (Programa $candidate) use ($attributes) {
            return ProgramaMunicipios::normalize($candidate->operador) === ProgramaMunicipios::normalize($attributes['operador'])
                && ProgramaMunicipios::normalize($candidate->municipio) === ProgramaMunicipios::normalize($attributes['municipio']);
        });
    }

    private function emptyRow(array $source): bool
    {
        return collect($source)->except('external_id')->every(fn ($value) => $this->clean($value) === null);
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function document(mixed $value): ?string
    {
        $value = $this->clean($value);
        if ($value === null || in_array(ProgramaMunicipios::normalize($value), ['NA', 'N/A'], true)) {
            return null;
        }

        return preg_replace('/\.0$/', '', $value);
    }
}
