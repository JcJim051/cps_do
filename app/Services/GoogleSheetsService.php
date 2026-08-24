<?php

namespace App\Services;

use App\Models\GoogleIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleSheetsService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SHEETS_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function authorizationUrl(): string
    {
        $state = Str::random(48);
        session(['google_oauth_state' => $state]);

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => route('prevalidacion.google.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'email',
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/drive.metadata.readonly',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(string $code, string $state): GoogleIntegration
    {
        abort_unless(hash_equals((string) session()->pull('google_oauth_state'), $state), 419, 'Estado OAuth inválido.');

        $payload = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('prevalidacion.google.callback'),
        ])->throw()->json();

        $email = Http::withToken($payload['access_token'])->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json('email');

        return GoogleIntegration::query()->updateOrCreate(['id' => 1], [
            'account_email' => $email,
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? GoogleIntegration::find(1)?->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3600)),
            'scopes' => explode(' ', (string) ($payload['scope'] ?? '')),
            'connected_at' => now(),
        ]);
    }

    public function disconnect(): void
    {
        $integration = GoogleIntegration::find(1);
        if ($integration?->access_token) {
            try {
                Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $integration->access_token]);
            } catch (\Throwable) {
                // La revocación remota es de mejor esfuerzo; siempre se elimina la sesión local.
            }
        }
        $integration?->update([
            'account_email' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => null,
            'connected_at' => null,
        ]);
    }

    public function values(string $spreadsheetId, string $sheet, int $headerRow = 1): array
    {
        $range = "'".str_replace("'", "''", $sheet)."'!A{$headerRow}:ZZ";
        $response = $this->client()->get(self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range));

        return $response->throw()->json('values', []);
    }

    public function updateCell(string $spreadsheetId, string $sheet, int $row, int $column, mixed $value): void
    {
        $cell = $this->columnLetter($column).$row;
        $range = "'".str_replace("'", "''", $sheet)."'!{$cell}";

        $this->client()->put(
            self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).'/values/'.rawurlencode($range).'?valueInputOption=RAW',
            ['range' => $range, 'majorDimension' => 'ROWS', 'values' => [[$value]]]
        )->throw();
    }

    public function updateCells(string $spreadsheetId, array $updates): void
    {
        foreach (array_chunk($updates, 200) as $chunk) {
            $data = array_map(function (array $update) {
                $sheet = "'".str_replace("'", "''", $update['sheet'])."'";
                $range = $sheet.'!'.$this->columnLetter((int) $update['column']).(int) $update['row'];
                return ['range' => $range, 'majorDimension' => 'ROWS', 'values' => [[$update['value']]]];
            }, $chunk);

            $this->client()->post(
                self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).'/values:batchUpdate',
                ['valueInputOption' => 'RAW', 'data' => $data]
            )->throw();
        }
    }

    public function sheetMetadata(string $spreadsheetId, string $sheet, ?int $row = null): array
    {
        $query = [
            'includeGridData' => $row !== null ? 'true' : 'false',
            'fields' => 'sheets(properties(sheetId,title,gridProperties(columnCount)),protectedRanges(protectedRangeId,description,range,warningOnly,editors),data(startRow,startColumn,rowData(values(dataValidation))))',
        ];
        if ($row !== null) {
            $query['ranges'] = "'".str_replace("'", "''", $sheet)."'!{$row}:{$row}";
        }

        $response = $this->client()->get(
            self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).'?'.http_build_query($query)
        )->throw()->json('sheets', []);

        return collect($response)->first(fn ($item) =>
            Str::lower((string) data_get($item, 'properties.title')) === Str::lower($sheet)
        )
            ?? throw new \RuntimeException("No se encontró la pestaña {$sheet}.");
    }

    public function cellMetadata(string $spreadsheetId, string $range): array
    {
        return $this->client()->get(self::SHEETS_URL.'/'.rawurlencode($spreadsheetId), [
            'includeGridData' => 'true',
            'ranges' => $range,
            'fields' => 'sheets(properties(sheetId,title),data(startRow,startColumn,rowData(values(userEnteredValue,effectiveValue,formattedValue,dataValidation,userEnteredFormat,effectiveFormat))))',
        ])->throw()->json('sheets', []);
    }

    public function transitionAndProtectRow(
        string $spreadsheetId,
        string $sheet,
        int $row,
        int $statusColumn,
        int $editedColumn,
        string $status,
        string $description,
        ?array $statusValidation = null,
        bool $writeEditedHeader = false,
        int $headerRow = 1,
        string $editedHeader = 'EDITADO_EN_INTEGRA',
    ): int {
        $metadata = $this->sheetMetadata($spreadsheetId, $sheet, $row);
        $sheetId = (int) data_get($metadata, 'properties.sheetId');
        $requests = [];

        if (($statusValidation['condition']['type'] ?? null) === 'ONE_OF_RANGE') {
            [$catalogRequests, $statusValidation] = $this->extendDropdownRange(
                $spreadsheetId, $statusValidation, $status
            );
            $requests = array_merge($requests, $catalogRequests);
            $requests[] = ['setDataValidation' => [
                'range' => $this->gridRange($sheetId, $row, $statusColumn),
                'rule' => $statusValidation,
            ]];
        } elseif (($statusValidation['condition']['type'] ?? null) === 'ONE_OF_LIST') {
            $values = collect($statusValidation['condition']['values'] ?? [])
                ->pluck('userEnteredValue')->filter()->values();
            if (!$values->contains(fn ($value) => Str::upper(Str::ascii($value)) === Str::upper(Str::ascii($status)))) {
                $values->push($status);
                $validation = $statusValidation;
                $validation['condition']['values'] = $values->map(fn ($value) => ['userEnteredValue' => $value])->all();
                $requests[] = ['setDataValidation' => [
                    'range' => $this->gridRange($sheetId, $row, $statusColumn),
                    'rule' => $validation,
                ]];
            }
        }

        if ($writeEditedHeader) {
            $requests[] = $this->cellUpdateRequest($sheetId, $headerRow, $editedColumn, $editedHeader);
        }
        $requests[] = $this->cellUpdateRequest($sheetId, $row, $statusColumn, $status);
        $requests[] = $this->contractingStatusFormatRequest($sheetId, $row, $statusColumn);
        $requests[] = $this->cellUpdateRequest($sheetId, $row, $editedColumn, 'SI');

        $existing = collect($metadata['protectedRanges'] ?? [])
            ->first(fn ($range) => ($range['description'] ?? null) === $description);
        $addProtectionIndex = null;
        $editorEmail = GoogleIntegration::find(1)?->account_email;
        $protectedRange = [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => $row - 1, 'endRowIndex' => $row],
            'description' => $description,
            'warningOnly' => false,
        ];
        if ($editorEmail) {
            $protectedRange['editors'] = ['users' => [$editorEmail]];
        }
        if (!$existing) {
            $addProtectionIndex = count($requests);
            $requests[] = ['addProtectedRange' => ['protectedRange' => $protectedRange]];
        } else {
            $protectedRange['protectedRangeId'] = (int) $existing['protectedRangeId'];
            $requests[] = ['updateProtectedRange' => [
                'protectedRange' => $protectedRange,
                'fields' => 'range,description,warningOnly,editors',
            ]];
        }

        $response = $this->client()->post(
            self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).':batchUpdate',
            ['requests' => $requests]
        )->throw()->json();

        return (int) ($existing['protectedRangeId']
            ?? data_get($response, "replies.{$addProtectionIndex}.addProtectedRange.protectedRange.protectedRangeId"));
    }

    public function protectColumnsWithWarning(string $spreadsheetId, string $sheet, array $columns): array
    {
        $metadata = $this->sheetMetadata($spreadsheetId, $sheet);
        $sheetId = (int) data_get($metadata, 'properties.sheetId');
        $protectedRanges = collect($metadata['protectedRanges'] ?? []);
        $requests = [];
        $ids = [];

        foreach ($columns as $header => $column) {
            $description = 'INTEGRA:ADVERTENCIA:COLUMNA:'.$header;
            $existing = $protectedRanges->first(fn ($range) => ($range['description'] ?? null) === $description);
            $protectedRange = [
                'range' => [
                    'sheetId' => $sheetId,
                    'startColumnIndex' => (int) $column,
                    'endColumnIndex' => (int) $column + 1,
                ],
                'description' => $description,
                'warningOnly' => true,
            ];

            if ($existing) {
                $protectedRange['protectedRangeId'] = (int) $existing['protectedRangeId'];
                $ids[$header] = (int) $existing['protectedRangeId'];
                $requests[] = ['updateProtectedRange' => [
                    'protectedRange' => $protectedRange,
                    'fields' => 'range,description,warningOnly',
                ]];
            } else {
                $requests[] = ['addProtectedRange' => ['protectedRange' => $protectedRange]];
            }
        }

        if (!$requests) return $ids;

        $response = $this->client()->post(
            self::SHEETS_URL.'/'.rawurlencode($spreadsheetId).':batchUpdate',
            ['requests' => $requests]
        )->throw()->json();

        foreach ($requests as $index => $request) {
            if (!isset($request['addProtectedRange'])) continue;
            $header = str_replace('INTEGRA:ADVERTENCIA:COLUMNA:', '', $request['addProtectedRange']['protectedRange']['description']);
            $ids[$header] = (int) data_get($response, "replies.{$index}.addProtectedRange.protectedRange.protectedRangeId");
        }

        return $ids;
    }

    private function extendDropdownRange(string $spreadsheetId, array $validation, string $status): array
    {
        $formula = (string) data_get($validation, 'condition.values.0.userEnteredValue');
        if (!preg_match("/^='?([^']+)'?!\\$?([A-Z]+)\\$?(\\d+):\\$?\\2\\$?(\\d+)$/i", $formula, $match)) {
            throw new \RuntimeException("No se pudo ampliar el catálogo del estado: {$formula}.");
        }
        [, $catalogSheet, $columnLetters, $startRow, $endRow] = $match;
        $column = $this->columnIndex($columnLetters);
        $rows = $this->values($spreadsheetId, $catalogSheet, 1);
        $catalogMetadata = $this->sheetMetadata($spreadsheetId, $catalogSheet);
        $catalogSheetId = (int) data_get($catalogMetadata, 'properties.sheetId');
        $normalize = fn ($value) => Str::upper(Str::ascii(trim((string) $value)));
        for ($row = (int) $startRow; $row <= (int) $endRow; $row++) {
            if ($normalize($rows[$row - 1][$column] ?? null) === $normalize($status)) {
                return [[$this->contractingStatusFormatRequest($catalogSheetId, $row, $column)], $validation];
            }
        }

        $newEndRow = (int) $endRow + 1;
        $nextValue = trim((string) ($rows[$newEndRow - 1][$column] ?? ''));
        if ($nextValue !== '' && $normalize($nextValue) !== $normalize($status)) {
            throw new \RuntimeException("No se amplió el catálogo: {$catalogSheet}!{$columnLetters}{$newEndRow} ya contiene {$nextValue}.");
        }
        $requests = $nextValue === '' ? [$this->cellUpdateRequest(
            $catalogSheetId, $newEndRow, $column, $status
        )] : [];
        $requests[] = $this->contractingStatusFormatRequest($catalogSheetId, $newEndRow, $column);
        $validation['condition']['values'][0]['userEnteredValue'] =
            "='{$catalogSheet}'!\${$columnLetters}\${$startRow}:\${$columnLetters}\${$newEndRow}";

        return [$requests, $validation];
    }

    private function contractingStatusFormatRequest(int $sheetId, int $row, int $column): array
    {
        return ['repeatCell' => [
            'range' => $this->gridRange($sheetId, $row, $column),
            'cell' => ['userEnteredFormat' => [
                'backgroundColorStyle' => ['rgbColor' => [
                    'red' => 106 / 255,
                    'green' => 13 / 255,
                    'blue' => 173 / 255,
                ]],
                'textFormat' => [
                    'foregroundColorStyle' => ['rgbColor' => ['red' => 1, 'green' => 1, 'blue' => 1]],
                    'bold' => true,
                ],
            ]],
            'fields' => 'userEnteredFormat.backgroundColorStyle,userEnteredFormat.textFormat.foregroundColorStyle,userEnteredFormat.textFormat.bold',
        ]];
    }

    private function cellUpdateRequest(int $sheetId, int $row, int $column, mixed $value): array
    {
        return ['updateCells' => [
            'range' => $this->gridRange($sheetId, $row, $column),
            'rows' => [['values' => [['userEnteredValue' => ['stringValue' => (string) $value]]]]],
            'fields' => 'userEnteredValue',
        ]];
    }

    private function gridRange(int $sheetId, int $row, int $column): array
    {
        return [
            'sheetId' => $sheetId,
            'startRowIndex' => $row - 1,
            'endRowIndex' => $row,
            'startColumnIndex' => $column,
            'endColumnIndex' => $column + 1,
        ];
    }

    private function columnIndex(string $letters): int
    {
        $result = 0;
        foreach (str_split(Str::upper($letters)) as $letter) {
            $result = ($result * 26) + ord($letter) - 64;
        }
        return $result - 1;
    }

    public function spreadsheetId(string $value): string
    {
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', $value, $matches)) {
            return $matches[1];
        }

        return trim($value);
    }

    private function client(): PendingRequest
    {
        $integration = GoogleIntegration::find(1);
        if (!$integration?->refresh_token && !$integration?->access_token) {
            throw new \RuntimeException('La cuenta de Google Drive no está conectada.');
        }

        if (!$integration->access_token || !$integration->token_expires_at || $integration->token_expires_at->lte(now()->addMinutes(2))) {
            $payload = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $integration->refresh_token,
                'grant_type' => 'refresh_token',
            ])->throw()->json();

            $integration->update([
                'access_token' => $payload['access_token'],
                'token_expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3600)),
            ]);
        }

        return Http::withToken($integration->fresh()->access_token)->acceptJson()->asJson()->timeout(30);
    }

    private function clientId(): string
    {
        return GoogleIntegration::find(1)?->oauth_client_id
            ?: throw new \RuntimeException('Falta configurar el Client ID de Google OAuth desde Prevalidación contractual → Configurar Google Drive.');
    }

    private function clientSecret(): string
    {
        return GoogleIntegration::find(1)?->oauth_client_secret
            ?: throw new \RuntimeException('Falta configurar el Client Secret de Google OAuth desde Prevalidación contractual → Configurar Google Drive.');
    }

    private function columnLetter(int $column): string
    {
        $result = '';
        for ($column++; $column > 0; $column = intdiv($column - 1, 26)) {
            $result = chr(65 + (($column - 1) % 26)).$result;
        }
        return $result;
    }
}
