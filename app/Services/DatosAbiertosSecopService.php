<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DatosAbiertosSecopService
{
    public function __construct(private SecopNormalizer $normalizer)
    {
    }

    public function consultarPorDocumento(string $documento, ?string $desde = null, int $limit = 1000): array
    {
        $secop2 = $this->consultarSecop2($documento, $desde, $limit);
        $secop1 = $this->consultarSecop1($documento, $desde, $limit);

        return collect(array_merge($secop2, $secop1))
            ->sortByDesc(function (array $row) {
                return $row['fecha_firma_sort'] ?? '';
            })
            ->values()
            ->all();
    }

    /** Consulta contratos cuya ejecución se cruza con la vigencia indicada. */
    public function consultarPorDocumentoVigencia(string $documento, int $anio, int $limit = 1000, array $nitEntidades = []): array
    {
        $desde = sprintf('%04d-01-01', $anio);
        $hasta = sprintf('%04d-01-01', $anio + 1);
        $vigencia = new SecopVigenciaService();

        return collect(array_merge(
            $this->consultarSecop2($documento, null, $limit, null, $desde, $hasta, $nitEntidades),
            $this->consultarSecop1($documento, null, $limit, null, $desde, $hasta, $nitEntidades),
        ))
            ->filter(fn (array $row) => $vigencia->contratoPertenece($row, $anio))
            ->when($nitEntidades !== [], fn ($rows) => $rows->filter(
                fn (array $row) => $this->nitEstaPermitido($row['nit_entidad'] ?? null, $nitEntidades)
            ))
            ->unique(fn (array $row) => ($row['fuente_codigo'] ?? '').'|'.($row['identificador_externo'] ?? ''))
            ->sortByDesc(fn (array $row) => $row['fecha_firma_sort'] ?? '')
            ->values()
            ->all();
    }

    /** Consulta contratos vinculados en lotes, usando el identificador estable de cada conjunto. */
    public function consultarPorIdentificadores(string $fuente, array $identificadores): array
    {
        $identificadores = collect($identificadores)->filter()->unique()->values();
        if ($identificadores->isEmpty()) return [];

        $rows = collect();
        foreach ($identificadores->chunk(40) as $chunk) {
            $field = $fuente === 'secop1' ? 'uid' : 'id_contrato';
            $usable = $chunk->filter(fn ($id) => $fuente !== 'secop1' || !str_contains((string) $id, '|'));
            if ($usable->isEmpty()) continue;
            $quoted = $usable->map(fn ($id) => "'".str_replace("'", "''", (string) $id)."'")->implode(',');
            $response = Http::timeout(25)->get(
                $fuente === 'secop1'
                    ? 'https://www.datos.gov.co/resource/f789-7hwg.json'
                    : 'https://www.datos.gov.co/resource/jbjy-vk9h.json',
                [
                    '$select' => $fuente === 'secop1' ? $this->secop1Select() : $this->secop2Select(),
                    '$where' => "{$field} in({$quoted})",
                    '$limit' => $usable->count(),
                ],
            );
            if (!$response->ok()) throw new \RuntimeException(strtoupper($fuente).' HTTP '.$response->status());
            $rows->push(...collect($response->json())->map(
                fn (array $row) => $fuente === 'secop1' ? $this->mapSecop1($row) : $this->mapSecop2($row)
            ));
        }

        return $rows->keyBy(fn (array $row) => (string) $row['identificador_externo'])->all();
    }

    public function consultarCandidatos(string $documento, ?string $nitEntidad = null, int $limit = 200): array
    {
        $contratos = array_merge(
            $this->consultarSecop2($documento, null, $limit, $nitEntidad),
            $this->consultarSecop1($documento, null, $limit, $nitEntidad)
        );

        $procesos = [];
        try {
            $procesos = $this->consultarProcesosSecop2($documento, $nitEntidad, min($limit, 100));
        } catch (\Throwable) {
            // Los contratos siguen siendo útiles aunque falle el conjunto de proponentes/procesos.
        }

        $contractProcessIds = collect($contratos)->pluck('proceso_de_compra')->filter()->all();
        $procesos = collect($procesos)
            ->reject(fn (array $row) => in_array($row['id_proceso'] ?? null, $contractProcessIds, true))
            ->all();

        return collect(array_merge($contratos, $procesos))
            ->sortByDesc(fn (array $row) => $row['fecha_firma_sort'] ?? $row['fecha_publicacion_sort'] ?? '')
            ->values()->all();
    }

    public function buscarCandidato(string $documento, ?string $nitEntidad, string $fuente, string $identificador): ?array
    {
        return collect($this->consultarCandidatos($documento, $nitEntidad))
            ->first(fn (array $row) => ($row['fuente_codigo'] ?? '') === $fuente
                && (string) ($row['identificador_externo'] ?? '') === $identificador);
    }

    public function consultarReferenciaEntidad(string $referencia, int $anio, string $nitEntidad, int $limit = 50): array
    {
        $normalized = $this->normalizer->contrato($referencia, $anio);
        if (!$normalized['consecutivo']) {
            return [];
        }

        $needle = str_replace("'", "''", $normalized['consecutivo']);
        $where = $this->nitWhere('nit_entidad', $nitEntidad, false)
            ." AND fecha_de_firma >= '{$anio}-01-01T00:00:00.000'"
            ." AND fecha_de_firma < '".($anio + 1)."-01-01T00:00:00.000'"
            ." AND upper(referencia_del_contrato) like '%{$needle}%'";

        $response = Http::retry(2, 500)->timeout(20)->get('https://www.datos.gov.co/resource/jbjy-vk9h.json', [
            '$select' => $this->secop2Select(),
            '$where' => $where,
            '$order' => 'fecha_de_firma DESC',
            '$limit' => $limit,
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('SECOP II HTTP '.$response->status());
        }

        return collect($response->json())
            ->map(fn (array $row) => $this->mapSecop2($row))
            ->filter(function (array $row) use ($normalized) {
                return $this->normalizer->contrato(
                    $row['referencia_contrato'] ?? '',
                    $row['fecha_firma'] ?? null,
                )['clave'] === $normalized['clave'];
            })
            ->values()
            ->all();
    }

    protected function consultarSecop2(
        string $documento,
        ?string $desde,
        int $limit,
        ?string $nitEntidad = null,
        ?string $vigenciaDesde = null,
        ?string $vigenciaHasta = null,
        array $nitEntidades = [],
    ): array
    {
        $baseUrl = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';
        $select = $this->secop2Select();

        $where = "documento_proveedor = '{$documento}'";
        if ($desde !== null && $desde !== '') {
            $where .= " AND fecha_de_firma >= '{$desde}T00:00:00.000'";
        }
        if ($nitEntidad) {
            $where .= ' AND '.$this->nitWhere('nit_entidad', $nitEntidad, false);
        }
        if ($vigenciaDesde && $vigenciaHasta) {
            $where .= " AND ((fecha_de_inicio_del_contrato IS NOT NULL"
                ." AND fecha_de_inicio_del_contrato < '{$vigenciaHasta}T00:00:00.000'"
                ." AND (fecha_de_fin_del_contrato IS NULL OR fecha_de_fin_del_contrato >= '{$vigenciaDesde}T00:00:00.000'))"
                ." OR (fecha_de_inicio_del_contrato IS NULL"
                ." AND fecha_de_firma >= '{$vigenciaDesde}T00:00:00.000'"
                ." AND fecha_de_firma < '{$vigenciaHasta}T00:00:00.000'))";
        }
        if ($nitEntidades !== []) {
            $where .= ' AND '.$this->nitsWhere('nit_entidad', $nitEntidades, false);
        }

        $response = Http::retry(2, 500)->timeout(20)->get($baseUrl, [
            '$select' => $select,
            '$where' => $where,
            '$order' => 'fecha_de_firma DESC',
            '$limit' => $limit,
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('SECOP II HTTP '.$response->status());
        }

        return collect($response->json())->map(fn (array $row) => $this->mapSecop2($row))->all();
    }

    protected function consultarSecop1(
        string $documento,
        ?string $desde,
        int $limit,
        ?string $nitEntidad = null,
        ?string $vigenciaDesde = null,
        ?string $vigenciaHasta = null,
        array $nitEntidades = [],
    ): array
    {
        $baseUrl = 'https://www.datos.gov.co/resource/f789-7hwg.json';
        $select = $this->secop1Select();

        $where = "identificacion_del_contratista = '{$documento}'";
        if ($desde !== null && $desde !== '') {
            $where .= " AND fecha_de_firma_del_contrato >= '{$desde}T00:00:00.000'";
        }
        if ($nitEntidad) {
            $where .= ' AND '.$this->nitWhere('nit_de_la_entidad', $nitEntidad, true);
        }
        if ($vigenciaDesde && $vigenciaHasta) {
            $where .= " AND ((fecha_ini_ejec_contrato IS NOT NULL"
                ." AND fecha_ini_ejec_contrato < '{$vigenciaHasta}T00:00:00.000'"
                ." AND (fecha_fin_ejec_contrato IS NULL OR fecha_fin_ejec_contrato >= '{$vigenciaDesde}T00:00:00.000'))"
                ." OR (fecha_ini_ejec_contrato IS NULL"
                ." AND fecha_de_firma_del_contrato >= '{$vigenciaDesde}T00:00:00.000'"
                ." AND fecha_de_firma_del_contrato < '{$vigenciaHasta}T00:00:00.000'))";
        }
        if ($nitEntidades !== []) {
            $where .= ' AND '.$this->nitsWhere('nit_de_la_entidad', $nitEntidades, true);
        }

        $response = Http::retry(2, 500)->timeout(20)->get($baseUrl, [
            '$select' => $select,
            '$where' => $where,
            '$order' => 'fecha_de_firma_del_contrato DESC',
            '$limit' => $limit,
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('SECOP I HTTP '.$response->status());
        }

        return collect($response->json())->map(fn (array $row) => $this->mapSecop1($row))->all();
    }

    private function secop1Select(): string
    {
        return implode(', ', [
            'uid', 'nombre_entidad', 'nit_de_la_entidad', 'c_digo_de_la_entidad',
            'departamento_entidad', 'municipio_entidad', 'numero_de_proceso', 'numero_de_contrato',
            'estado_del_proceso', 'tipo_de_contrato', 'modalidad_de_contratacion',
            'fecha_de_firma_del_contrato', 'fecha_ini_ejec_contrato', 'fecha_fin_ejec_contrato',
            'plazo_de_ejec_del_contrato', 'rango_de_ejec_del_contrato',
            'tiempo_adiciones_en_dias', 'tiempo_adiciones_en_meses', 'marcacion_adiciones',
            'cuantia_contrato', 'valor_total_de_adiciones', 'valor_contrato_con_adiciones',
            'ultima_actualizacion', 'nom_razon_social_contratista', 'identificacion_del_contratista',
            'ruta_proceso_en_secop_i', 'objeto_del_contrato_a_la',
        ]);
    }

    private function mapSecop1(array $row): array
    {
        return [
                'fuente' => 'SECOP I',
                'fuente_codigo' => 'secop1',
                'nombre_entidad' => $row['nombre_entidad'] ?? null,
                'nit_entidad' => $row['nit_de_la_entidad'] ?? null,
                'departamento' => $row['departamento_entidad'] ?? null,
                'ciudad' => $row['municipio_entidad'] ?? null,
                'proceso_de_compra' => $row['numero_de_proceso'] ?? null,
                'id_proceso' => $row['numero_de_proceso'] ?? null,
                'id_contrato' => null,
                'referencia_contrato' => $row['numero_de_contrato'] ?? $row['numero_de_proceso'] ?? null,
                'identificador_externo' => $row['uid'] ?? implode('|', array_filter([
                    $row['c_digo_de_la_entidad'] ?? $row['nit_de_la_entidad'] ?? null,
                    $row['numero_de_proceso'] ?? null,
                ])),
                'identificador_legacy' => implode('|', array_filter([
                    $row['c_digo_de_la_entidad'] ?? $row['nit_de_la_entidad'] ?? null,
                    $row['numero_de_proceso'] ?? null,
                ])),
                'tipo_registro' => 'contrato',
                'estado' => $row['estado_del_proceso'] ?? null,
                'tipo' => $row['tipo_de_contrato'] ?? null,
                'modalidad' => $row['modalidad_de_contratacion'] ?? null,
                'fecha_firma' => $this->formatDate($row['fecha_de_firma_del_contrato'] ?? null),
                'fecha_firma_sort' => $this->sortDate($row['fecha_de_firma_del_contrato'] ?? null),
                'fecha_inicio' => $this->formatDate($row['fecha_ini_ejec_contrato'] ?? null),
                'fecha_fin' => $this->formatDate($row['fecha_fin_ejec_contrato'] ?? null),
                'duracion_inicial' => $row['plazo_de_ejec_del_contrato'] ?? null,
                'unidad_duracion' => $row['rango_de_ejec_del_contrato'] ?? null,
                'dias_adicionados' => $row['tiempo_adiciones_en_dias'] ?? null,
                'meses_adicionados' => $row['tiempo_adiciones_en_meses'] ?? null,
                'marcacion_adicion' => $row['marcacion_adiciones'] ?? null,
                'ultima_actualizacion_fuente' => $row['ultima_actualizacion'] ?? null,
                'valor_contrato' => $row['cuantia_contrato'] ?? null,
                'valor_adiciones' => $row['valor_total_de_adiciones'] ?? null,
                'valor_total_con_adiciones' => $row['valor_contrato_con_adiciones'] ?? null,
                'proveedor' => $row['nom_razon_social_contratista'] ?? null,
                'documento' => $row['identificacion_del_contratista'] ?? null,
                'objeto' => $row['objeto_del_contrato_a_la'] ?? null,
                'url' => $this->normalizeUrl($row['ruta_proceso_en_secop_i'] ?? ''),
            ];
    }

    protected function consultarProcesosSecop2(string $documento, ?string $nitEntidad, int $limit): array
    {
        $where = "nit_proveedor = '".$this->digits($documento)."'";
        if ($nitEntidad) {
            $where .= ' AND '.$this->nitWhere('nit_entidad', $nitEntidad, true);
        }

        $proponentes = Http::timeout(15)->get('https://www.datos.gov.co/resource/hgi6-6wh3.json', [
            '$select' => 'id_procedimiento,fecha_publicaci_n,nombre_procedimiento,nit_entidad,entidad_compradora,nit_proveedor',
            '$where' => $where,
            '$order' => 'fecha_publicaci_n DESC',
            '$limit' => $limit,
        ])->throw()->json();

        $ids = collect($proponentes)->pluck('id_procedimiento')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $quoted = $ids->map(fn ($id) => "'".str_replace("'", "''", $id)."'")->implode(',');
        $rows = Http::timeout(18)->get('https://www.datos.gov.co/resource/p6dx-8zbt.json', [
            '$select' => implode(',', [
                'entidad', 'nit_entidad', 'id_del_proceso', 'referencia_del_proceso', 'nombre_del_procedimiento',
                'descripci_n_del_procedimiento', 'fase', 'estado_del_procedimiento', 'estado_resumen',
                'fecha_de_publicacion_del', 'fecha_de_ultima_publicaci', 'precio_base', 'duracion',
                'unidad_de_duracion', 'tipo_de_contrato', 'urlproceso',
            ]),
            '$where' => "id_del_proceso in({$quoted})",
            '$limit' => $ids->count(),
        ])->throw()->json();

        return collect($rows)->map(function (array $row) use ($documento) {
            return [
                'fuente' => 'SECOP II',
                'fuente_codigo' => 'secop2_proceso',
                'tipo_registro' => 'proceso',
                'identificador_externo' => $row['id_del_proceso'] ?? null,
                'id_proceso' => $row['id_del_proceso'] ?? null,
                'referencia_proceso' => $row['referencia_del_proceso'] ?? null,
                'proceso_de_compra' => $row['id_del_proceso'] ?? null,
                'nombre_entidad' => $row['entidad'] ?? null,
                'nit_entidad' => $row['nit_entidad'] ?? null,
                'estado' => $row['estado_del_procedimiento'] ?? $row['estado_resumen'] ?? null,
                'fase' => $row['fase'] ?? $row['estado_resumen'] ?? null,
                'tipo' => $row['tipo_de_contrato'] ?? null,
                'fecha_publicacion' => $this->formatDate($row['fecha_de_publicacion_del'] ?? null),
                'fecha_publicacion_sort' => $this->sortDate($row['fecha_de_publicacion_del'] ?? null),
                'fecha_firma' => null,
                'fecha_firma_sort' => '',
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'valor_contrato' => $row['precio_base'] ?? null,
                'valor_adiciones' => null,
                'valor_total_con_adiciones' => $row['precio_base'] ?? null,
                'documento' => $documento,
                'objeto' => $row['descripci_n_del_procedimiento'] ?? $row['nombre_del_procedimiento'] ?? null,
                'url' => $this->normalizeUrl($row['urlproceso'] ?? ''),
            ];
        })->all();
    }

    protected function formatDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function nitWhere(string $field, string $nit, bool $quoted): string
    {
        $values = $this->normalizer->variantesNit($nit);
        if ($values === []) {
            return '1 = 0';
        }

        $conditions = collect($values)->map(function (string $value) use ($field, $quoted) {
            $safe = preg_replace('/\D+/', '', $value) ?? '';
            return $field.' = '.($quoted ? "'{$safe}'" : $safe);
        });

        return '('.$conditions->implode(' OR ').')';
    }

    private function nitsWhere(string $field, array $nits, bool $quoted): string
    {
        $conditions = collect($nits)
            ->map(fn ($nit) => $this->nitWhere($field, (string) $nit, $quoted))
            ->filter(fn (string $condition) => $condition !== '1 = 0')
            ->unique()
            ->values();

        return $conditions->isEmpty() ? '1 = 0' : '('.$conditions->implode(' OR ').')';
    }

    private function nitEstaPermitido(mixed $nit, array $permitidos): bool
    {
        foreach ($permitidos as $permitido) {
            if ($this->normalizer->nitCoincide((string) $permitido, $nit)) {
                return true;
            }
        }

        return false;
    }

    private function secop2Select(): string
    {
        return implode(', ', [
            'nombre_entidad', 'nit_entidad', 'departamento', 'ciudad', 'proceso_de_compra',
            'id_contrato', 'referencia_del_contrato', 'estado_contrato', 'tipo_de_contrato',
            'modalidad_de_contratacion', 'fecha_de_firma', 'fecha_de_inicio_del_contrato',
            'fecha_de_fin_del_contrato', 'valor_del_contrato', 'proveedor_adjudicado',
            'duraci_n_del_contrato', 'dias_adicionados', 'ultima_actualizacion',
            'documento_proveedor', 'urlproceso', 'objeto_del_contrato',
        ]);
    }

    private function mapSecop2(array $row): array
    {
        return [
            'fuente' => 'SECOP II',
            'fuente_codigo' => 'secop2',
            'nombre_entidad' => $row['nombre_entidad'] ?? null,
            'nit_entidad' => $row['nit_entidad'] ?? null,
            'departamento' => $row['departamento'] ?? null,
            'ciudad' => $row['ciudad'] ?? null,
            'proceso_de_compra' => $row['proceso_de_compra'] ?? null,
            'id_proceso' => $row['proceso_de_compra'] ?? null,
            'id_contrato' => $row['id_contrato'] ?? null,
            'referencia_contrato' => $row['referencia_del_contrato'] ?? null,
            'identificador_externo' => $row['id_contrato'] ?? $row['referencia_del_contrato'] ?? $row['proceso_de_compra'] ?? null,
            'tipo_registro' => 'contrato',
            'estado' => $row['estado_contrato'] ?? null,
            'tipo' => $row['tipo_de_contrato'] ?? null,
            'modalidad' => $row['modalidad_de_contratacion'] ?? null,
            'fecha_firma' => $this->formatDate($row['fecha_de_firma'] ?? null),
            'fecha_firma_sort' => $this->sortDate($row['fecha_de_firma'] ?? null),
            'fecha_inicio' => $this->formatDate($row['fecha_de_inicio_del_contrato'] ?? null),
            'fecha_fin' => $this->formatDate($row['fecha_de_fin_del_contrato'] ?? null),
            'duracion_inicial' => $row['duraci_n_del_contrato'] ?? null,
            'unidad_duracion' => null,
            'dias_adicionados' => $row['dias_adicionados'] ?? null,
            'meses_adicionados' => null,
            'marcacion_adicion' => null,
            'ultima_actualizacion_fuente' => $row['ultima_actualizacion'] ?? null,
            'valor_contrato' => $row['valor_del_contrato'] ?? null,
            'valor_adiciones' => null,
            'valor_total_con_adiciones' => $row['valor_del_contrato'] ?? null,
            'proveedor' => $row['proveedor_adjudicado'] ?? null,
            'documento' => $row['documento_proveedor'] ?? null,
            'objeto' => $row['objeto_del_contrato'] ?? null,
            'url' => $this->normalizeUrl($row['urlproceso'] ?? ''),
        ];
    }

    protected function sortDate($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function normalizeUrl($url): string
    {
        if (is_array($url)) {
            $url = $url['url'] ?? json_encode($url, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($url) && str_starts_with($url, '{') && str_contains($url, '"url"')) {
            $decoded = json_decode($url, true);
            if (is_array($decoded) && !empty($decoded['url'])) {
                $url = $decoded['url'];
            }
        }

        $url = is_string($url) ? trim($url) : '';
        if ($url && !str_starts_with($url, 'http')) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
