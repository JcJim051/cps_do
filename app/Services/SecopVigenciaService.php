<?php

namespace App\Services;

use App\Models\Seguimiento;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SecopVigenciaService
{
    private const ESTADOS_CONTRACTUALES = [
        'EN EJECUCION', 'MODIFICADO', 'PRORROGADO', 'CEDIDO', 'CELEBRADO', 'SUSPENDIDO',
        'CERRADO', 'TERMINADO', 'LIQUIDADO', 'TERMINADO SIN LIQUIDAR',
    ];

    public function contratoPertenece(array $contrato, int $anio): bool
    {
        $inicio = $this->date($contrato['fecha_inicio'] ?? null);
        $fin = $this->date($contrato['fecha_fin'] ?? null);
        $firma = $this->date($contrato['fecha_firma'] ?? null);
        [$desde, $hasta] = $this->bounds($anio);

        if ($inicio) {
            return $inicio->lte($hasta) && (!$fin || $fin->gte($desde));
        }

        // Cuando SECOP todavía no publica las fechas de ejecución, la firma
        // dentro de la vigencia es la única evidencia temporal disponible.
        return $firma?->betweenIncluded($desde, $hasta) ?? false;
    }

    public function seguimientoPertenece(Seguimiento $seguimiento, int $anio): bool
    {
        if ((int) $seguimiento->anio === $anio) {
            return true;
        }

        $inicio = $this->date($seguimiento->fecha_acta_inicio);
        $fin = $this->date($seguimiento->fecha_finalizacion_adicion ?: $seguimiento->fecha_finalizacion);
        if ($this->overlaps($inicio, $fin, $anio)) {
            return true;
        }

        $snapshot = $seguimiento->vinculoSecop?->ultimaInstantanea;
        return $snapshot
            ? $this->overlaps($this->date($snapshot->fecha_inicio), $this->date($snapshot->fecha_fin), $anio)
            : false;
    }

    public function tipoHallazgoContrato(?string $estado): string
    {
        return in_array($this->normalize($estado), self::ESTADOS_CONTRACTUALES, true)
            ? 'solo_secop'
            : 'alerta_secop';
    }

    private function overlaps(?Carbon $inicio, ?Carbon $fin, int $anio): bool
    {
        if (!$inicio) {
            return false;
        }
        [$desde, $hasta] = $this->bounds($anio);
        return $inicio->lte($hasta) && (!$fin || $fin->gte($desde));
    }

    /** @return array{Carbon,Carbon} */
    private function bounds(int $anio): array
    {
        return [Carbon::create($anio, 1, 1)->startOfDay(), Carbon::create($anio, 12, 31)->endOfDay()];
    }

    private function date(mixed $value): ?Carbon
    {
        if (!$value) return null;
        try {
            return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(trim(Str::ascii((string) $value)));
    }
}
