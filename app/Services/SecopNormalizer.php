<?php

namespace App\Services;

use Illuminate\Support\Str;

class SecopNormalizer
{
    public function documento(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return ltrim($digits, '0') ?: $digits;
    }

    /**
     * SECOP no es uniforme con el dígito de verificación: algunas entidades lo
     * publican y otras no. Conservamos ambas variantes para consultar y comparar.
     */
    public function variantesNit(mixed $value): array
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];
        if (str_contains($raw, '-')) {
            $base = preg_replace('/\D+/', '', Str::beforeLast($raw, '-')) ?? '';
            if ($base !== '') {
                $variants[] = $base;
            }
        } elseif (strlen($digits) >= 10) {
            $variants[] = substr($digits, 0, -1);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function nitCoincide(mixed $left, mixed $right): bool
    {
        return count(array_intersect($this->variantesNit($left), $this->variantesNit($right))) > 0;
    }

    /** @return array{consecutivo:?string,anio:?int,clave:?string} */
    public function contrato(mixed $value, mixed $yearHint = null): array
    {
        $text = Str::upper(Str::ascii(trim((string) $value)));
        $year = null;
        if (is_numeric($yearHint)) {
            $year = (int) $yearHint;
        } elseif (preg_match('/\b(?:19|20)\d{2}\b/', (string) $yearHint, $hintYear)) {
            $year = (int) $hintYear[0];
        } elseif (preg_match_all('/\b(?:19|20)\d{2}\b/', $text, $years) && $years[0] !== []) {
            $year = (int) end($years[0]);
        }

        $withoutYears = $year
            ? (preg_replace('/\b'.preg_quote((string) $year, '/').'\b/', ' ', $text) ?? $text)
            : $text;
        preg_match_all('/\d+/', $withoutYears, $numbers);
        $parts = collect($numbers[0] ?? [])
            ->map(fn (string $number) => ltrim($number, '0') ?: '0')
            ->values();

        $consecutive = $parts->isNotEmpty() ? $parts->implode('-') : null;
        if ($consecutive === null && $text !== '') {
            $fallback = preg_replace('/\b(CONTRATO|CTO|CPS|NUMERO|NRO|NO|DE)\b/', '', $withoutYears) ?? '';
            $fallback = preg_replace('/[^A-Z0-9]+/', '', $fallback) ?? '';
            $consecutive = $fallback !== '' ? $fallback : null;
        }

        return [
            'consecutivo' => $consecutive,
            'anio' => $year,
            'clave' => $consecutive !== null && $year !== null ? $consecutive.'|'.$year : null,
        ];
    }

    public function diferenciaPorcentual(mixed $planned, mixed $observed): ?float
    {
        if (!is_numeric($planned) || !is_numeric($observed) || (float) $planned <= 0) {
            return null;
        }

        return round((((float) $observed - (float) $planned) / (float) $planned) * 100, 2);
    }
}
