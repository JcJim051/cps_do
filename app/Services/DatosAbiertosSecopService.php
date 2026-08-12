<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DatosAbiertosSecopService
{
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

    protected function consultarSecop2(string $documento, ?string $desde, int $limit): array
    {
        $baseUrl = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';
        $select = implode(', ', [
            'nombre_entidad',
            'departamento',
            'ciudad',
            'proceso_de_compra',
            'estado_contrato',
            'tipo_de_contrato',
            'modalidad_de_contratacion',
            'fecha_de_firma',
            'fecha_de_inicio_del_contrato',
            'fecha_de_fin_del_contrato',
            'valor_del_contrato',
            'proveedor_adjudicado',
            'documento_proveedor',
            'urlproceso',
            'objeto_del_contrato',
        ]);

        $where = "documento_proveedor = '{$documento}'";
        if ($desde !== null && $desde !== '') {
            $where .= " AND fecha_de_firma >= '{$desde}T00:00:00.000'";
        }

        $response = Http::timeout(12)->get($baseUrl, [
            '$select' => $select,
            '$where' => $where,
            '$order' => 'fecha_de_firma DESC',
            '$limit' => $limit,
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('SECOP II HTTP '.$response->status());
        }

        return collect($response->json())->map(function (array $row) {
            return [
                'fuente' => 'SECOP II',
                'fuente_codigo' => 'secop2',
                'nombre_entidad' => $row['nombre_entidad'] ?? null,
                'departamento' => $row['departamento'] ?? null,
                'ciudad' => $row['ciudad'] ?? null,
                'proceso_de_compra' => $row['proceso_de_compra'] ?? null,
                'estado' => $row['estado_contrato'] ?? null,
                'tipo' => $row['tipo_de_contrato'] ?? null,
                'modalidad' => $row['modalidad_de_contratacion'] ?? null,
                'fecha_firma' => $this->formatDate($row['fecha_de_firma'] ?? null),
                'fecha_firma_sort' => $this->sortDate($row['fecha_de_firma'] ?? null),
                'fecha_inicio' => $this->formatDate($row['fecha_de_inicio_del_contrato'] ?? null),
                'fecha_fin' => $this->formatDate($row['fecha_de_fin_del_contrato'] ?? null),
                'valor_contrato' => $row['valor_del_contrato'] ?? null,
                'valor_adiciones' => null,
                'valor_total_con_adiciones' => $row['valor_del_contrato'] ?? null,
                'proveedor' => $row['proveedor_adjudicado'] ?? null,
                'documento' => $row['documento_proveedor'] ?? null,
                'objeto' => $row['objeto_del_contrato'] ?? null,
                'url' => $this->normalizeUrl($row['urlproceso'] ?? ''),
            ];
        })->all();
    }

    protected function consultarSecop1(string $documento, ?string $desde, int $limit): array
    {
        $baseUrl = 'https://www.datos.gov.co/resource/f789-7hwg.json';
        $select = implode(', ', [
            'nombre_entidad',
            'departamento_entidad',
            'municipio_entidad',
            'numero_de_proceso',
            'estado_del_proceso',
            'tipo_de_contrato',
            'modalidad_de_contratacion',
            'fecha_de_firma_del_contrato',
            'fecha_ini_ejec_contrato',
            'fecha_fin_ejec_contrato',
            'cuantia_contrato',
            'valor_total_de_adiciones',
            'valor_contrato_con_adiciones',
            'nom_razon_social_contratista',
            'identificacion_del_contratista',
            'ruta_proceso_en_secop_i',
            'objeto_del_contrato_a_la',
        ]);

        $where = "identificacion_del_contratista = '{$documento}'";
        if ($desde !== null && $desde !== '') {
            $where .= " AND fecha_de_firma_del_contrato >= '{$desde}T00:00:00.000'";
        }

        $response = Http::timeout(12)->get($baseUrl, [
            '$select' => $select,
            '$where' => $where,
            '$order' => 'fecha_de_firma_del_contrato DESC',
            '$limit' => $limit,
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('SECOP I HTTP '.$response->status());
        }

        return collect($response->json())->map(function (array $row) {
            return [
                'fuente' => 'SECOP I',
                'fuente_codigo' => 'secop1',
                'nombre_entidad' => $row['nombre_entidad'] ?? null,
                'departamento' => $row['departamento_entidad'] ?? null,
                'ciudad' => $row['municipio_entidad'] ?? null,
                'proceso_de_compra' => $row['numero_de_proceso'] ?? null,
                'estado' => $row['estado_del_proceso'] ?? null,
                'tipo' => $row['tipo_de_contrato'] ?? null,
                'modalidad' => $row['modalidad_de_contratacion'] ?? null,
                'fecha_firma' => $this->formatDate($row['fecha_de_firma_del_contrato'] ?? null),
                'fecha_firma_sort' => $this->sortDate($row['fecha_de_firma_del_contrato'] ?? null),
                'fecha_inicio' => $this->formatDate($row['fecha_ini_ejec_contrato'] ?? null),
                'fecha_fin' => $this->formatDate($row['fecha_fin_ejec_contrato'] ?? null),
                'valor_contrato' => $row['cuantia_contrato'] ?? null,
                'valor_adiciones' => $row['valor_total_de_adiciones'] ?? null,
                'valor_total_con_adiciones' => $row['valor_contrato_con_adiciones'] ?? null,
                'proveedor' => $row['nom_razon_social_contratista'] ?? null,
                'documento' => $row['identificacion_del_contratista'] ?? null,
                'objeto' => $row['objeto_del_contrato_a_la'] ?? null,
                'url' => $this->normalizeUrl($row['ruta_proceso_en_secop_i'] ?? ''),
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
}
