<?php

namespace App\Services;

use App\Models\Gerencia;
use App\Models\Persona;
use App\Models\PrevalidacionContractual;
use App\Models\PrevalidacionEvento;
use App\Models\PrevalidacionFuente;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrevalidacionSyncService
{
    public const CAMPOS = [
        'numero_origen', 'dependencia', 'anio', 'gerencia', 'estado', 'nombre_contratista',
        'cedula_o_nit', 'celular', 'nivel_academico', 'profesion', 'especializacion', 'maestria',
        'fuente_recursos', 'numero_contrato', 'fecha_inicio', 'fecha_fin', 'tiempo_dias',
        'valor_mensual', 'valor_total', 'adicion', 'fecha_inicio_adicion', 'fecha_fin_adicion',
        'tiempo_adicion_dias', 'tiempo_total_dias', 'valor_adicion', 'valor_total_contrato',
        'evaluacion', 'continua', 'observaciones', 'sector', 'enlace', 'programa',
    ];

    public function __construct(private GoogleSheetsService $sheets)
    {
    }

    public function sincronizar(PrevalidacionFuente $fuente): array
    {
        $headerRow = (int) ($fuente->fila_encabezados ?: 1);
        $rows = $this->sheets->values($fuente->spreadsheet_id, $fuente->hoja, $headerRow);
        if (count($rows) < 1) {
            throw new \RuntimeException('La hoja no contiene encabezados.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        $indexes = $this->indexes($headers);
        $mapping = $fuente->mapeo_columnas ?: [];
        $missing = collect(['cedula_o_nit', 'nombre_contratista', 'estado', 'anio'])
            ->filter(fn ($field) => !isset($mapping[$field]) || !isset($indexes[$this->normalize($mapping[$field])]))
            ->values()->all();
        if ($missing) {
            throw new \RuntimeException('Faltan columnas obligatorias en la hoja: '.implode(', ', $missing));
        }

        $idHeader = $fuente->columna_clave ?: 'ID_INTEGRA';
        $idKey = $this->normalize($idHeader);
        $idHeaderWasMissing = !isset($indexes[$idKey]);
        $idIndex = $indexes[$idKey] ?? count($headers);
        if ($idHeaderWasMissing) {
            $headers[$idIndex] = $idHeader;
            $indexes[$idKey] = $idIndex;
        }

        $editedHeader = $fuente->columna_editado ?: 'EDITADO_EN_INTEGRA';
        $editedKey = $this->normalize($editedHeader);
        $editedHeaderWasMissing = !isset($indexes[$editedKey]);
        $editedIndex = $indexes[$editedKey] ?? count($headers);
        if ($editedHeaderWasMissing) {
            $headers[$editedIndex] = $editedHeader;
            $indexes[$editedKey] = $editedIndex;
        }

        $usedIds = [];
        foreach ($rows as $row) {
            $existingId = trim((string) ($row[$idIndex] ?? ''));
            if ($existingId !== '') $usedIds[$existingId] = true;
        }

        $prepared = [];
        $seen = [];
        $sequences = [];
        $driveUpdates = [];
        $allowedStates = array_map(fn ($state) => $this->normalize($state), config('prevalidacion.estados_drive_ingreso', ['APROBADO']));
        $result = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'ids_generados' => 0, 'errores' => []];

        foreach ($rows as $offset => $row) {
            $rowNumber = $headerRow + $offset + 1;
            $source = [];
            foreach (self::CAMPOS as $field) {
                $header = $mapping[$field] ?? null;
                $source[$field] = $header && isset($indexes[$this->normalize($header)])
                    ? ($row[$indexes[$this->normalize($header)]] ?? null)
                    : null;
            }

            $year = $this->year($source['anio']);
            if ($fuente->anio_objetivo && $year !== (int) $fuente->anio_objetivo) {
                $result['omitidos']++;
                continue;
            }
            if (!in_array($this->normalize($source['estado']), $allowedStates, true)) {
                $result['omitidos']++;
                continue;
            }
            $document = $this->document($source['cedula_o_nit']);
            if ($document === '' && collect($source)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                $result['omitidos']++;
                continue;
            }

            $external = trim((string) ($row[$idIndex] ?? ''));
            if ($external === '') {
                $base = $document !== '' ? $document : strtoupper(Str::slug($fuente->nombre, '')).'-SD';
                $sequenceKey = $base.'-'.$year;
                do {
                    $sequence = ($sequences[$sequenceKey] ?? 0) + 1;
                    $sequences[$sequenceKey] = $sequence;
                    $external = $base.'-'.$year.'-'.str_pad((string) $sequence, $document !== '' ? 2 : 3, '0', STR_PAD_LEFT);
                } while (isset($usedIds[$external]));
                $usedIds[$external] = true;
                $driveUpdates[] = ['sheet' => $fuente->hoja, 'row' => $rowNumber, 'column' => $idIndex, 'value' => $external];
                $result['ids_generados']++;
            }
            if (isset($seen[$external])) {
                $result['errores'][] = "Filas {$seen[$external]} y {$rowNumber}: clave de origen duplicada {$external}.";
                continue;
            }
            $seen[$external] = $rowNumber;

            $fullRow = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') $fullRow[$header] = $index === $idIndex ? $external : ($row[$index] ?? null);
            }
            $prepared[] = compact('source', 'document', 'external', 'row', 'rowNumber', 'fullRow');
        }

        if ($idHeaderWasMissing) {
            array_unshift($driveUpdates, ['sheet' => $fuente->hoja, 'row' => $headerRow, 'column' => $idIndex, 'value' => $idHeader]);
        }
        if ($editedHeaderWasMissing) {
            array_unshift($driveUpdates, ['sheet' => $fuente->hoja, 'row' => $headerRow, 'column' => $editedIndex, 'value' => $editedHeader]);
        }
        if ($driveUpdates) {
            $this->sheets->updateCells($fuente->spreadsheet_id, $driveUpdates);
        }
        $this->sheets->protectColumnsWithWarning($fuente->spreadsheet_id, $fuente->hoja, [
            $idHeader => $idIndex,
            $editedHeader => $editedIndex,
        ]);

        $fuente->prevalidaciones()->update(['presente_en_origen' => false]);

        foreach ($prepared as $item) {
            ['source' => $source, 'document' => $document, 'external' => $external, 'row' => $row, 'rowNumber' => $rowNumber, 'fullRow' => $fullRow] = $item;
            try {
                DB::transaction(function () use ($fuente, $source, $document, $external, $row, $rowNumber, $fullRow, &$result) {
                    $record = PrevalidacionContractual::query()
                        ->where('fuente_id', $fuente->id)->where('clave_externa', $external)->first();
                    $isNew = !$record;
                    $record ??= new PrevalidacionContractual(['fuente_id' => $fuente->id, 'clave_externa' => $external]);
                    $overrides = $record->campos_locales ?: [];
                    $conflicts = [];
                    $mapped = $this->mappedAttributes($fuente, $source, $document);

                    foreach ($mapped as $attribute => $value) {
                        if (!$isNew && in_array($attribute, $overrides, true)) {
                            if ($this->comparable($record->{$attribute}) !== $this->comparable($value)) {
                                $conflicts[$attribute] = ['drive' => $value, 'integra' => $record->{$attribute}];
                            }
                            continue;
                        }
                        if ($attribute === 'estado' && !$isNew && $record->estado_gestionado_localmente) {
                            if ($record->estado !== $value) {
                                $conflicts['estado'] = ['drive' => $value, 'integra' => $record->estado];
                            }
                            continue;
                        }
                        $record->{$attribute} = $value;
                    }

                    $record->fila_origen = $rowNumber;
                    $record->persona_id = Persona::query()->where('cedula_o_nit', $document)->value('id');
                    $record->etapa = $record->seguimiento_id
                        ? 'EN_SEGUIMIENTO'
                        : ($record->estado_gestionado_localmente ? $this->stageFromInternalState($record->estado) : $this->stage($source['estado']));
                    $record->datos_origen = ['mapeados' => $source, 'fila' => $fullRow];
                    $record->conflictos = $conflicts ?: null;
                    $record->hash_origen = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
                    $record->presente_en_origen = true;
                    $record->ultima_sincronizacion_at = now();
                    $record->save();

                    if ($record->estado_gestionado_localmente) {
                        $this->writeBack($record);
                    }

                    $result[$isNew ? 'creados' : 'actualizados']++;
                });
            } catch (\Throwable $e) {
                $result['errores'][] = "Fila {$rowNumber}: {$e->getMessage()}";
            }
        }

        $fuente->update([
            'ultima_sincronizacion_at' => now(),
            'ultimo_error' => $result['errores'] ? implode("\n", array_slice($result['errores'], 0, 20)) : null,
        ]);

        return $result;
    }

    public function writeBack(PrevalidacionContractual $record): void
    {
        $fuente = $record->fuente;
        if (!$record->fila_origen) {
            return;
        }

        $headerRow = (int) ($fuente->fila_encabezados ?: 1);
        $rows = $this->sheets->values($fuente->spreadsheet_id, $fuente->hoja, $headerRow);
        $headers = array_map(fn ($value) => trim((string) $value), $rows[0] ?? []);
        $indexes = $this->indexes($headers);
        $statusHeader = $fuente->columna_estado ?: ($fuente->mapeo_columnas['estado'] ?? 'ESTADO');
        $statusIndex = $indexes[$this->normalize($statusHeader)] ?? null;
        if ($statusIndex === null) {
            throw new \RuntimeException("No se encontró la columna de estado {$statusHeader}.");
        }

        $editedHeader = $fuente->columna_editado ?: 'EDITADO_EN_INTEGRA';
        $editedIndex = $indexes[$this->normalize($editedHeader)] ?? count($headers);
        if (!isset($indexes[$this->normalize($editedHeader)])) {
            $this->sheets->updateCell($fuente->spreadsheet_id, $fuente->hoja, $headerRow, $editedIndex, $editedHeader);
        }

        $this->sheets->updateCell($fuente->spreadsheet_id, $fuente->hoja, $record->fila_origen, $statusIndex, $record->estado);
        $this->sheets->updateCell($fuente->spreadsheet_id, $fuente->hoja, $record->fila_origen, $editedIndex, 'SI');
    }

    public function moveToTracking(PrevalidacionContractual $record, ?int $userId): void
    {
        $context = $this->trackingRowContext($record);
        ['fuente' => $fuente, 'headerRow' => $headerRow, 'headers' => $headers,
            'indexes' => $indexes, 'statusIndex' => $statusIndex, 'row' => $row] = $context;

        $editedHeader = $fuente->columna_editado ?: 'EDITADO_EN_INTEGRA';
        $editedKey = $this->normalize($editedHeader);
        $writeEditedHeader = !isset($indexes[$editedKey]);
        $editedIndex = $indexes[$editedKey] ?? count($headers);
        $metadata = $this->sheets->sheetMetadata($fuente->spreadsheet_id, $fuente->hoja, $row);
        $statusValidation = data_get($metadata, 'data.0.rowData.0.values.'.$statusIndex.'.dataValidation');
        $description = 'INTEGRA:'.$record->clave_externa;
        $protectedRangeId = $this->sheets->transitionAndProtectRow(
            $fuente->spreadsheet_id,
            $fuente->hoja,
            $row,
            $statusIndex,
            $editedIndex,
            'EN CONTRATACIÓN',
            $description,
            $statusValidation,
            $writeEditedHeader,
            $headerRow,
            $editedHeader,
        );

        $record->update([
            'fila_origen' => $row,
            'estado_origen' => 'EN CONTRATACIÓN',
            'etapa' => 'EN_SEGUIMIENTO',
            'editado_en_integra' => true,
            'presente_en_origen' => false,
            'drive_protected_range_id' => $protectedRangeId ?: null,
            'drive_protegido_at' => now(),
            'drive_sincronizacion_pendiente' => false,
            'drive_ultimo_error' => null,
        ]);

        PrevalidacionEvento::create([
            'prevalidacion_id' => $record->id,
            'user_id' => $userId,
            'tipo' => 'drive_en_contratacion',
            'despues' => ['estado' => 'EN CONTRATACIÓN', 'fila' => $row, 'protected_range_id' => $protectedRangeId],
        ]);
    }

    public function assertReadyForTracking(PrevalidacionContractual $record): void
    {
        $this->trackingRowContext($record);
    }

    private function trackingRowContext(PrevalidacionContractual $record): array
    {
        $record->loadMissing('fuente');
        $fuente = $record->fuente;
        $headerRow = (int) ($fuente->fila_encabezados ?: 1);
        $rows = $this->sheets->values($fuente->spreadsheet_id, $fuente->hoja, $headerRow);
        $headers = array_map(fn ($value) => trim((string) $value), $rows[0] ?? []);
        $indexes = $this->indexes($headers);
        $idHeader = $fuente->columna_clave ?: 'ID_INTEGRA';
        $idIndex = $indexes[$this->normalize($idHeader)] ?? null;
        $statusHeader = $fuente->columna_estado ?: ($fuente->mapeo_columnas['estado'] ?? 'ESTADO');
        $statusIndex = $indexes[$this->normalize($statusHeader)] ?? null;
        if ($idIndex === null || $statusIndex === null) {
            throw new \RuntimeException('No se encontraron las columnas ID_INTEGRA y estado en el cuadro de origen.');
        }

        $matches = collect(array_slice($rows, 1))->map(function ($sourceRow, $offset) use ($headerRow, $idIndex, $statusIndex) {
            return [
                'offset' => $offset,
                'row' => $headerRow + $offset + 1,
                'id' => trim((string) ($sourceRow[$idIndex] ?? '')),
                'status' => $this->normalize($sourceRow[$statusIndex] ?? null),
            ];
        })->where('id', $record->clave_externa)->values();
        if ($matches->isEmpty()) {
            throw new \RuntimeException("No se encontró el ID {$record->clave_externa} en Drive; no se modificó otra fila.");
        }

        $allowed = fn ($item) => in_array($item['status'], ['APROBADO', 'EN CONTRATACION'], true);
        $selected = $matches->first(fn ($item) => $item['row'] === (int) $record->fila_origen && $allowed($item));
        $eligible = $matches->filter($allowed)->values();
        $selected ??= $eligible->count() === 1 ? $eligible->first() : null;
        if (!$selected) {
            if ($eligible->count() > 1) {
                throw new \RuntimeException("El ID {$record->clave_externa} aparece en varias filas aprobadas; debe corregirse la duplicidad antes de continuar.");
            }
            $states = $matches->pluck('status')->filter()->unique()->implode(', ') ?: 'SIN ESTADO';
            throw new \RuntimeException("La fila cambió en Drive a {$states}; no se creó ni sobrescribió el seguimiento.");
        }

        return compact('fuente', 'headerRow', 'headers', 'indexes', 'statusIndex') + ['row' => $selected['row']];
    }

    public function markLocalEdit(PrevalidacionContractual $record, array $fields, ?int $userId): void
    {
        $before = $record->getOriginal();
        $local = array_values(array_unique(array_merge($record->campos_locales ?: [], $fields)));
        $record->campos_locales = $local;
        $record->editado_en_integra = true;
        if (in_array('estado', $fields, true)) {
            $record->estado_gestionado_localmente = true;
            $record->estado_origen = $record->estado;
            $record->etapa = $record->seguimiento_id ? 'EN_SEGUIMIENTO' : $this->stageFromInternalState($record->estado);
            if ($record->estado === 'APROBADO') {
                $record->aprobado_at ??= now();
                $record->aprobado_por = $userId;
            } else {
                $record->aprobado_at = null;
                $record->aprobado_por = null;
            }
        }
        $record->save();

        PrevalidacionEvento::create([
            'prevalidacion_id' => $record->id,
            'user_id' => $userId,
            'tipo' => 'edicion',
            'antes' => Arr::only($before, $fields),
            'despues' => Arr::only($record->fresh()->toArray(), $fields),
        ]);

        $this->writeBack($record->fresh('fuente'));
    }

    public function acceptDriveValue(PrevalidacionContractual $record, string $field, ?int $userId): void
    {
        $conflicts = $record->conflictos ?: [];
        if (!array_key_exists($field, $conflicts)) {
            throw new \RuntimeException('El conflicto seleccionado ya no existe.');
        }
        $before = $record->{$field};
        $record->{$field} = $conflicts[$field]['drive'] ?? null;
        $record->campos_locales = array_values(array_diff($record->campos_locales ?: [], [$field]));
        unset($conflicts[$field]);
        $record->conflictos = $conflicts ?: null;
        if ($field === 'estado') {
            $record->estado_gestionado_localmente = false;
            $record->estado_origen = $record->estado;
            $record->etapa = $record->seguimiento_id ? 'EN_SEGUIMIENTO' : $this->stage($record->estado);
        }
        $record->save();
        PrevalidacionEvento::create([
            'prevalidacion_id' => $record->id,
            'user_id' => $userId,
            'tipo' => 'acepta_valor_drive',
            'antes' => [$field => $before],
            'despues' => [$field => $record->{$field}],
        ]);
    }

    private function mappedAttributes(PrevalidacionFuente $fuente, array $source, string $document): array
    {
        $reportedName = $this->nullableString($source['nombre_contratista']);
        $requiresReview = $this->nameRequiresReview($reportedName);
        $knownName = $document !== '' ? Persona::query()->where('cedula_o_nit', $document)->value('nombre_contratista') : null;

        return [
            'cedula_o_nit' => $document,
            'numero_origen' => $this->nullableString($source['numero_origen']),
            'nombre_reportado' => $reportedName,
            'nombre_contratista' => $knownName ?: ($requiresReview ? null : $reportedName),
            'nombre_requiere_revision' => $requiresReview,
            'secretaria_id' => $fuente->secretaria_id,
            'gerencia_id' => $this->gerenciaId($fuente, $source['gerencia'] ?: $source['dependencia']),
            'dependencia_origen' => $this->nullableString($source['dependencia']),
            'programa_origen' => $this->nullableString($source['programa']),
            'celular' => $this->nullableString($source['celular']),
            'nivel_academico' => $this->nullableString($source['nivel_academico']),
            'profesion' => $this->nullableString($source['profesion']),
            'especializacion' => $this->nullableString($source['especializacion']),
            'maestria' => $this->nullableString($source['maestria']),
            'fuente_recursos' => $this->nullableString($source['fuente_recursos']),
            'estado' => $this->status($source['estado']),
            'estado_origen' => $this->nullableString($source['estado']),
            'anio' => $this->year($source['anio']),
            'numero_contrato_planeado' => $this->nullableString($source['numero_contrato']),
            'fecha_inicio_planeada' => $this->date($source['fecha_inicio']),
            'fecha_fin_planeada' => $this->date($source['fecha_fin']),
            'tiempo_planeado_dias' => $this->integer($source['tiempo_dias']),
            'valor_mensual_planeado' => $this->money($source['valor_mensual']),
            'valor_total_planeado' => $this->money($source['valor_total']),
            'adicion' => $this->nullableString($source['adicion']),
            'fecha_inicio_adicion' => $this->date($source['fecha_inicio_adicion']),
            'fecha_fin_adicion' => $this->date($source['fecha_fin_adicion']),
            'tiempo_adicion_dias' => $this->integer($source['tiempo_adicion_dias']),
            'tiempo_total_dias' => $this->integer($source['tiempo_total_dias']),
            'valor_adicion' => $this->money($source['valor_adicion']),
            'valor_total_contrato' => $this->money($source['valor_total_contrato']),
            'evaluacion' => $this->nullableString($source['evaluacion']),
            'continua' => $this->nullableString($source['continua']),
            'observaciones' => $this->nullableString($source['observaciones']),
            'sector' => $this->nullableString($source['sector']),
            'enlace_origen' => $this->nullableString($source['enlace']),
        ];
    }

    private function indexes(array $headers): array
    {
        $result = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $result[$this->normalize($header)] = $index;
            }
        }
        return $result;
    }

    private function gerenciaId(PrevalidacionFuente $fuente, mixed $value): ?int
    {
        $needle = $this->normalize($value);
        if ($needle === '') {
            return null;
        }
        return Gerencia::query()->where('secretaria_id', $fuente->secretaria_id)->get()
            ->first(fn ($item) => $this->normalize($item->nombre) === $needle)?->id;
    }

    private function status(mixed $value): string
    {
        $value = $this->normalize($value);
        return match (true) {
            str_contains($value, 'APROB') && !str_contains($value, 'PEND') => 'APROBADO',
            str_contains($value, 'CAMBIO') => 'CAMBIO',
            default => 'PENDIENTE',
        };
    }

    private function stage(mixed $value): string
    {
        $value = $this->normalize($value);
        return match (true) {
            str_contains($value, 'CONTRATADO'), str_contains($value, 'NO CONTINUA'),
            str_contains($value, 'LIQUIDADO'), str_contains($value, 'TERMINADO'), str_contains($value, 'CERRADO') => 'HISTORIAL',
            str_contains($value, 'APROB') && !str_contains($value, 'PEND') => 'APROBADO_POR_ENVIAR',
            default => 'POR_GESTIONAR',
        };
    }

    private function stageFromInternalState(mixed $value): string
    {
        return $this->normalize($value) === 'APROBADO' ? 'APROBADO_POR_ENVIAR' : 'POR_GESTIONAR';
    }

    private function nameRequiresReview(?string $value): bool
    {
        if (!$value) return true;

        return mb_strlen($value) > 80
            || preg_match('/[\r\n]/', $value)
            || preg_match('/\b(CAMBIO|REEMPLAZO|CEDID[OA]|CESI[ÓO]N|PTE|PENDIENTE)\b/iu', $value)
            || preg_match('/\b(exp|especialista|ingeniero|t[eé]cnico|tecn[oó]logo|profesional)\b/iu', $value)
            || preg_match('/\b(Pro|Tecno)\s*\+/iu', $value);
    }

    private function normalize(mixed $value): string
    {
        return Str::upper(Str::ascii(trim((string) $value)));
    }

    private function document(mixed $value): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', trim((string) $value));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function year(mixed $value): ?int
    {
        if (preg_match('/(20\d{2})/', (string) $value, $match)) {
            return (int) $match[1];
        }
        return null;
    }

    private function integer(mixed $value): ?int
    {
        $number = $this->money($value);
        return $number === null ? null : (int) round($number);
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            if (is_numeric($value) && $value > 25569) {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }
            $text = trim((string) $value);
            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $match)) {
                $year = (int) $match[3];
                if ($year < 2000 || $year > 2100) return null;
                return Carbon::createSafe($year, (int) $match[2], (int) $match[1])->toDateString();
            }
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function comparable(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return trim((string) ($value ?? ''));
    }
}
