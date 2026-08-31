<?php

namespace App\Services;

use App\Models\Estados;
use App\Models\SecopActualizacionSeguimiento;
use App\Models\SecopInstantanea;
use App\Models\SecopVinculo;
use App\Models\Seguimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecopAplicacionService
{
    public const MANAGED_FIELDS = [
        'numero_contrato', 'anio', 'estado_contrato_id', 'fecha_acta_inicio', 'fecha_finalizacion',
        'tiempo_ejecucion_dias', 'valor_total', 'adicion', 'fecha_finalizacion_adicion',
        'tiempo_ejecucion_dias_adicion', 'tiempo_total_ejecucion_dias', 'valor_adicion',
        'tiempo_extension_secop_dias', 'tiempo_suspension_dias', 'tiempo_total_calendario_dias',
        'valor_total_contrato',
    ];

    public function preview(SecopVinculo $link, ?SecopInstantanea $snapshot = null): array
    {
        $snapshot ??= $link->ultimaInstantanea;
        if (!$link->seguimiento || !$snapshot) {
            return ['resultado' => 'sin_datos', 'cambios' => [], 'omitidos' => [], 'origenes' => [], 'alertas' => ['No existe una instantánea SECOP aplicable.']];
        }

        $seguimiento = $link->seguimiento;
        [$values, $origins, $alerts] = $this->valuesFrom($seguimiento, $snapshot);
        $excluded = $link->campos_excluidos ?? [];
        $changes = [];
        $skipped = [];

        foreach ($values as $field => $value) {
            if ($value === null) continue;
            if (in_array($field, $excluded, true)) {
                $skipped[$field] = ['actual' => $this->scalar($seguimiento->{$field}), 'secop' => $value, 'razon' => 'Edición manual'];
                continue;
            }
            $old = $this->scalar($seguimiento->{$field});
            if (!$this->same($seguimiento->{$field}, $value)) $changes[$field] = ['anterior' => $old, 'nuevo' => $value];
        }

        return [
            'resultado' => $changes === [] ? ($skipped === [] ? 'sin_cambios' : 'excluido') : 'actualizable',
            'cambios' => $changes,
            'omitidos' => $skipped,
            'origenes' => array_intersect_key($origins, $values),
            'alertas' => $alerts,
        ];
    }

    public function apply(SecopVinculo $link, string $mode = 'manual', ?int $userId = null, bool $dryRun = false): array
    {
        $link->loadMissing(['seguimiento', 'ultimaInstantanea']);
        $snapshot = $link->ultimaInstantanea;
        $preview = $this->preview($link, $snapshot);
        if ($dryRun || !$snapshot || !$link->seguimiento) return $preview + ['dry_run' => $dryRun];

        if ($preview['cambios'] === []) {
            $confirmedOrigins = array_diff_key($preview['origenes'], $preview['omitidos']);
            $link->forceFill([
                'origenes_campos' => array_replace($link->origenes_campos ?? [], $confirmedOrigins),
                'ultima_aplicacion_at' => now(), 'ultimo_error' => null,
                'ultimo_resultado' => $this->summary($preview),
            ])->save();
            return $preview;
        }

        return DB::transaction(function () use ($link, $snapshot, $preview, $mode, $userId) {
            $seguimiento = $link->seguimiento;
            $before = collect($preview['cambios'])->mapWithKeys(fn ($v, $k) => [$k => $v['anterior']])->all();
            $after = collect($preview['cambios'])->mapWithKeys(fn ($v, $k) => [$k => $v['nuevo']])->all();
            $origins = array_intersect_key($preview['origenes'], $after);

            $seguimiento->skipSecopExclusions()->skipAutoCalculation()->fill($after)->save();
            $fieldOrigins = array_replace(
                $link->origenes_campos ?? [],
                array_diff_key($preview['origenes'], $preview['omitidos']),
            );
            $link->forceFill([
                'origenes_campos' => $fieldOrigins,
                'ultima_aplicacion_at' => now(),
                'ultimo_error' => null,
                'ultimo_resultado' => $this->summary($preview),
            ])->save();

            SecopActualizacionSeguimiento::create([
                'seguimiento_id' => $seguimiento->id,
                'vinculo_id' => $link->id,
                'instantanea_id' => $snapshot->id,
                'user_id' => $userId,
                'fuente_secop' => $link->fuente_secop,
                'identificador_externo' => $link->identificador_externo,
                'modo' => $mode,
                'resultado' => 'actualizado',
                'valores_anteriores' => $before,
                'valores_nuevos' => $after,
                'campos_aplicados' => array_keys($after),
                'campos_omitidos' => $preview['omitidos'],
                'origenes' => $origins,
                'alertas' => $preview['alertas'],
                'hash_aplicacion' => hash('sha256', $seguimiento->id.'|'.$snapshot->hash.'|'.json_encode($after)),
                'aplicado_at' => now(),
            ]);

            return array_merge($preview, ['resultado' => 'actualizado']);
        });
    }

    public function restoreField(SecopVinculo $link, string $field, ?int $userId): array
    {
        abort_unless(in_array($field, self::MANAGED_FIELDS, true), 422, 'Campo SECOP inválido.');
        $excluded = array_values(array_diff($link->campos_excluidos ?? [], [$field]));
        $link->forceFill(['campos_excluidos' => $excluded])->save();
        return $this->apply($link->fresh(['seguimiento', 'ultimaInstantanea']), 'reactivacion', $userId);
    }

    public function revert(SecopActualizacionSeguimiento $update, ?int $userId): void
    {
        DB::transaction(function () use ($update, $userId) {
            $seguimiento = $update->seguimiento;
            $before = $update->valores_anteriores ?? [];
            $current = collect(array_keys($before))->mapWithKeys(fn ($field) => [$field => $this->scalar($seguimiento->{$field})])->all();
            $seguimiento->skipSecopExclusions()->skipAutoCalculation()->fill($before)->save();
            $link = $seguimiento->vinculoSecop;
            if ($link) {
                $excluded = array_values(array_unique(array_merge($link->campos_excluidos ?? [], array_keys($before))));
                $origins = $link->origenes_campos ?? [];
                foreach (array_keys($before) as $field) $origins[$field] = 'Manual';
                $link->forceFill(['campos_excluidos' => $excluded, 'origenes_campos' => $origins])->save();
            }
            SecopActualizacionSeguimiento::create([
                'seguimiento_id' => $seguimiento->id, 'vinculo_id' => $link?->id,
                'instantanea_id' => $update->instantanea_id, 'user_id' => $userId,
                'fuente_secop' => $update->fuente_secop, 'identificador_externo' => $update->identificador_externo,
                'modo' => 'reversion', 'resultado' => 'revertido',
                'valores_anteriores' => $current, 'valores_nuevos' => $before,
                'campos_aplicados' => array_keys($before), 'campos_omitidos' => [],
                'origenes' => array_fill_keys(array_keys($before), 'Manual'), 'alertas' => [],
                'aplicado_at' => now(),
            ]);
        });
    }

    private function valuesFrom(Seguimiento $s, SecopInstantanea $i): array
    {
        $values = ['estado_secop' => $i->estado];
        $origins = ['estado_secop' => 'SECOP'];
        $alerts = [];
        if ($i->numero_contrato) { $values['numero_contrato'] = trim($i->numero_contrato); $origins['numero_contrato'] = 'SECOP'; }
        if ($i->fecha_firma) { $values['anio'] = (int) $i->fecha_firma->format('Y'); $origins['anio'] = 'SECOP'; }
        if ($i->fecha_inicio) { $values['fecha_acta_inicio'] = $i->fecha_inicio->format('Y-m-d'); $origins['fecha_acta_inicio'] = 'SECOP'; }
        if ($i->ultima_actualizacion_fuente) $values['ultima_actualizacion_secop'] = $i->ultima_actualizacion_fuente;

        $state = $this->operationalState($i->estado);
        if ($state) { $values['estado_contrato_id'] = $state; $origins['estado_contrato_id'] = 'SECOP'; }
        elseif ($i->estado) $alerts[] = 'Estado SECOP sin mapeo operativo: '.$i->estado;

        $baseDays = $i->duracion_inicial_dias;
        $addedDays = (int) ($i->dias_adicionados ?? 0) + (int) round(((float) ($i->meses_adicionados ?? 0)) * 30);
        $source = $i->vinculo?->fuente_secop;
        if ($i->meses_adicionados > 0) $alerts[] = 'Meses adicionados convertidos con la regla de 30 días por mes.';
        if ($baseDays !== null) {
            $values['tiempo_ejecucion_dias'] = (int) $baseDays;
            $origins['tiempo_ejecucion_dias'] = $i->unidad_duracion && !str_contains(mb_strtolower($i->unidad_duracion), 'dia') ? 'Convertido' : 'SECOP';
            if ($i->fecha_inicio) {
                $values['fecha_finalizacion'] = $i->fecha_inicio->copy()->addDays(max(0, $baseDays - 1))->format('Y-m-d');
                $origins['fecha_finalizacion'] = 'Derivado';
            }
        } elseif (filled(data_get($i->datos, 'duracion_inicial'))) {
            $alerts[] = 'La duración publicada no pudo interpretarse; se conservó el valor local.';
        }

        $hasAddition = $addedDays > 0 || (bool) $i->marcacion_adicion || $this->signalsAddition($i->estado);
        if ($hasAddition) {
            $values['adicion'] = 'SI'; $origins['adicion'] = 'SECOP';
            if ($addedDays > 0 && $source !== 'secop2') {
                $values['tiempo_ejecucion_dias_adicion'] = $addedDays;
                $origins['tiempo_ejecucion_dias_adicion'] = $i->meses_adicionados > 0 ? 'Convertido' : 'SECOP';
            }
            if ($i->fecha_fin) { $values['fecha_finalizacion_adicion'] = $i->fecha_fin->format('Y-m-d'); $origins['fecha_finalizacion_adicion'] = 'SECOP'; }
        } elseif ($i->fecha_fin) {
            $values['fecha_finalizacion'] = $i->fecha_fin->format('Y-m-d'); $origins['fecha_finalizacion'] = 'SECOP';
        }
        if (($baseDays !== null || $addedDays > 0) && $source !== 'secop2') {
            $values['tiempo_total_ejecucion_dias'] = (int) ($baseDays ?? $s->tiempo_ejecucion_dias ?? 0) + $addedDays;
            $origins['tiempo_total_ejecucion_dias'] = 'Derivado';
        }
        if ($source === 'secop2' && $addedDays > 0) {
            $values['tiempo_extension_secop_dias'] = $addedDays;
            $origins['tiempo_extension_secop_dias'] = 'SECOP';
            $values['tiempo_total_calendario_dias'] = (int) ($baseDays ?? $s->tiempo_ejecucion_dias ?? 0) + $addedDays;
            $origins['tiempo_total_calendario_dias'] = 'Derivado';
        }

        $base = $i->valor_contrato !== null ? (float) $i->valor_contrato : null;
        $current = $i->valor_total !== null ? (float) $i->valor_total : null;
        $exactAddition = $i->valor_adiciones !== null ? (float) $i->valor_adiciones : null;
        if ($i->vinculo?->fuente_secop === 'secop2') {
            $localBase = (float) ($s->valor_total ?: 0);
            if ($hasAddition && $localBase > 0 && $current !== null && $current > $localBase) {
                $base = $localBase; $exactAddition = $current - $localBase;
                $origins['valor_adicion'] = 'Derivado';
            } elseif ($current !== null) {
                if ($localBase > 0 && $current < $localBase) $alerts[] = 'SECOP publicó una reducción de valor; no se creó una adición negativa.';
                $base = $current;
            }
        }
        if ($base !== null) { $values['valor_total'] = $base; $origins['valor_total'] = 'SECOP'; }
        if ($exactAddition !== null && $exactAddition > 0) {
            $values['valor_adicion'] = $exactAddition;
            $origins['valor_adicion'] ??= 'SECOP';
            $values['adicion'] = 'SI'; $origins['adicion'] = 'SECOP';
        }
        if ($source === 'secop2' && $exactAddition !== null && $exactAddition > 0 && (float) $s->valor_mensual > 0) {
            $calculated = $exactAddition / ((float) $s->valor_mensual / 30);
            $rounded = (int) round($calculated);
            if (abs($calculated - $rounded) <= .05) {
                $values['tiempo_ejecucion_dias_adicion'] = $rounded;
                $origins['tiempo_ejecucion_dias_adicion'] = 'Derivado';
                $values['tiempo_total_ejecucion_dias'] = (int) ($baseDays ?? $s->tiempo_ejecucion_dias ?? 0) + $rounded;
                $origins['tiempo_total_ejecucion_dias'] = 'Derivado';
                if ($addedDays >= $rounded) {
                    $values['tiempo_suspension_dias'] = $addedDays - $rounded;
                    $origins['tiempo_suspension_dias'] = 'Derivado';
                } else {
                    $alerts[] = 'Los días de adición derivados del valor superan la extensión publicada por SECOP.';
                }
            } else {
                $alerts[] = 'El valor adicional no equivale a un número entero confiable de días; no se descompuso la extensión SECOP.';
            }
        }
        if ($current !== null) { $values['valor_total_contrato'] = $current; $origins['valor_total_contrato'] = 'SECOP'; }
        elseif ($base !== null || $exactAddition !== null) {
            $values['valor_total_contrato'] = (float) ($base ?? $s->valor_total ?? 0) + (float) ($exactAddition ?? 0);
            $origins['valor_total_contrato'] = 'Derivado';
        }
        return [$values, $origins, $alerts];
    }

    private function operationalState(?string $raw): ?int
    {
        $state = $this->normalize($raw);
        $name = match (true) {
            in_array($state, ['EN EJECUCION', 'MODIFICADO', 'PRORROGADO', 'CEDIDO', 'CELEBRADO'], true) => 'CONTRATADO',
            in_array($state, ['CERRADO', 'TERMINADO', 'LIQUIDADO', 'TERMINADO SIN LIQUIDAR'], true) => 'LIQUIDADO',
            $state === 'SUSPENDIDO' => 'SUSPENDIDO',
            in_array($state, ['BORRADOR', 'APROBADO', 'EN APROBACION', 'ENVIADO A PROVEEDOR', 'ENVIADO PROVEEDOR', 'CONVOCADO', 'ADJUDICADO'], true) => 'APROBADO',
            in_array($state, ['CANCELADO', 'TERMINADO ANORMALMENTE', 'TERMINADO ANORMALMENTE DESPUES DE CONVOCADO'], true) => 'CANCELADO',
            default => null,
        };
        return $name ? Estados::query()->whereRaw('UPPER(nombre) = ?', [$name])->value('id') : null;
    }

    private function signalsAddition(?string $state): bool { return in_array($this->normalize($state), ['MODIFICADO', 'PRORROGADO'], true); }
    private function normalize(?string $value): string
    {
        return mb_strtoupper(Str::ascii(trim((string) $value)));
    }
    private function same(mixed $a, mixed $b): bool
    {
        if ($a instanceof \DateTimeInterface && is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b)) {
            return $a->format('Y-m-d') === $b;
        }
        if (is_numeric($a) && is_numeric($b)) return abs((float) $a - (float) $b) < .005;
        return (string) $this->scalar($a) === (string) $this->scalar($b);
    }
    private function scalar(mixed $value): mixed { return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value; }
    private function summary(array $r): array { return ['resultado' => $r['resultado'], 'aplicados' => count($r['cambios']), 'omitidos' => count($r['omitidos']), 'alertas' => $r['alertas']]; }
}
