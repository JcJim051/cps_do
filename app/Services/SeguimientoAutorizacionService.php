<?php

namespace App\Services;

use App\Models\Estados;
use App\Models\Secretaria;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeguimientoAutorizacionService
{
    private const ESTADOS_SIN_AUTORIZACION = [
        'PENDIENTE',
        'PENDIENTE APROBACION',
        'CAMBIO',
    ];

    /**
     * Mantiene estado y autorizaciones sincronizados antes de guardar.
     * El modelo recibido puede ser Seguimiento o Autorizacion: ambos usan
     * la tabla seguimientos.
     */
    public function synchronize(Model $seguimiento): void
    {
        if ($seguimiento->tipo !== 'contrato') {
            return;
        }

        $stateChanged = $seguimiento->isDirty('estado_contrato_id');
        $authorizationChanged = $seguimiento->isDirty('aut_despacho');
        $additionChanged = $seguimiento->isDirty('adicion');
        $additionAuthorizationChanged = $seguimiento->isDirty('aut_despacho_adicion');
        $secretariaChanged = $seguimiento->isDirty('secretaria_id');

        // Cuando ambos valores llegan en el formulario, el cambio de estado
        // tiene prioridad sobre el valor false que envía un switch apagado.
        if ($stateChanged && $seguimiento->estado_contrato_id) {
            $state = $this->stateName($seguimiento->estado_contrato_id);

            if ($state === 'APROBADO') {
                $this->grant($seguimiento, 'aut_despacho', 'fecha_aut_despacho');
            } elseif (in_array($state, self::ESTADOS_SIN_AUTORIZACION, true)) {
                $this->revoke($seguimiento, 'aut_despacho', 'fecha_aut_despacho');
            }
        } elseif ($authorizationChanged) {
            if ((bool) $seguimiento->aut_despacho) {
                $this->grant($seguimiento, 'aut_despacho', 'fecha_aut_despacho');
                $seguimiento->estado_contrato_id = $this->stateId('APROBADO');
            } else {
                $seguimiento->estado_contrato_id = $this->stateId('PENDIENTE APROBACION');
                $seguimiento->fecha_aut_despacho = null;
            }
        }

        // La selección manual de Adición conserva su automatización actual.
        // Un cambio explícito del check desde Autorizaciones prevalece cuando
        // el campo Adición no fue modificado en la misma operación.
        if ($additionChanged) {
            $addition = $this->normalize($seguimiento->adicion);
            if ($addition === 'SI') {
                $this->grant($seguimiento, 'aut_despacho_adicion', 'fecha_aut_despacho_adicion');
            } elseif ($addition === 'NO') {
                $this->revoke($seguimiento, 'aut_despacho_adicion', 'fecha_aut_despacho_adicion');
            }
        } elseif ($additionAuthorizationChanged) {
            if ((bool) $seguimiento->aut_despacho_adicion) {
                $this->grant($seguimiento, 'aut_despacho_adicion', 'fecha_aut_despacho_adicion');
            } else {
                $seguimiento->fecha_aut_despacho_adicion = null;
            }
        }

        $isDecentralized = $this->isDecentralized($seguimiento->secretaria_id);
        $wasDecentralized = $secretariaChanged
            ? $this->isDecentralized($seguimiento->getOriginal('secretaria_id'))
            : $isDecentralized;

        if ($isDecentralized) {
            if ((bool) $seguimiento->aut_despacho && $this->currentState($seguimiento) === 'APROBADO') {
                $this->grant($seguimiento, 'aut_administrativa', 'fecha_aut_administrativa');
            } else {
                $this->revoke($seguimiento, 'aut_administrativa', 'fecha_aut_administrativa');
            }

            if ((bool) $seguimiento->aut_despacho_adicion) {
                $this->grant($seguimiento, 'aut_administrativa_adicion', 'fecha_aut_administrativa_adicion');
            } else {
                $this->revoke($seguimiento, 'aut_administrativa_adicion', 'fecha_aut_administrativa_adicion');
            }
        } elseif ($secretariaChanged && $wasDecentralized) {
            // Al dejar de ser descentralizada se rompe la condición que había
            // concedido automáticamente la tercera autorización.
            $this->revoke($seguimiento, 'aut_administrativa', 'fecha_aut_administrativa');
            $this->revoke($seguimiento, 'aut_administrativa_adicion', 'fecha_aut_administrativa_adicion');
        }
    }

    private function grant(Model $model, string $field, string $dateField): void
    {
        $model->{$field} = true;
        $model->{$dateField} ??= Carbon::now('America/Bogota')->toDateString();
    }

    private function revoke(Model $model, string $field, string $dateField): void
    {
        $model->{$field} = false;
        $model->{$dateField} = null;
    }

    private function isDecentralized(mixed $secretariaId): bool
    {
        if (!$secretariaId) {
            return false;
        }

        return (bool) Secretaria::query()->whereKey($secretariaId)->value('es_descentralizada');
    }

    private function currentState(Model $model): string
    {
        return $model->estado_contrato_id ? $this->stateName($model->estado_contrato_id) : '';
    }

    private function stateName(mixed $id): string
    {
        return $this->normalize(Estados::query()->whereKey($id)->value('nombre'));
    }

    private function stateId(string $name): int
    {
        $normalized = $this->normalize($name);

        $id = Estados::query()
            ->get(['id', 'nombre'])
            ->first(fn (Estados $state) => $this->normalize($state->nombre) === $normalized)
            ?->id;

        if (!$id) {
            throw new \RuntimeException("No existe el estado {$name} en el catálogo de estados.");
        }

        return (int) $id;
    }

    private function normalize(mixed $value): string
    {
        return Str::upper(Str::ascii(trim((string) $value)));
    }
}
