<?php

namespace App\Services;

use App\Models\PrevalidacionFuente;
use App\Models\Secretaria;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SecopAlcanceEntidadService
{
    /** @return array{secretaria_ids:array<int,int>,nit_entidades:array<int,string>,faltantes:array<int,string>} */
    public function configuracion(): array
    {
        $secretarias = Secretaria::query()
            ->where('auditoria_secop_incluida', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit_secop']);

        $nitsFuente = Schema::hasTable('prevalidacion_fuentes')
            ? PrevalidacionFuente::query()
                ->where('activa', true)
                ->whereIn('secretaria_id', $secretarias->pluck('id'))
                ->whereNotNull('nit_entidad')
                ->pluck('nit_entidad', 'secretaria_id')
            : collect();

        $resolved = $secretarias->mapWithKeys(function (Secretaria $secretaria) use ($nitsFuente) {
            $nit = $this->digits($secretaria->nit_secop ?: $nitsFuente->get($secretaria->id));
            return [$secretaria->id => $nit !== '' ? $nit : null];
        });

        return [
            'secretaria_ids' => $secretarias->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'nit_entidades' => $resolved->filter()->unique()->values()->all(),
            'faltantes' => $secretarias
                ->filter(fn (Secretaria $secretaria) => !$resolved->get($secretaria->id))
                ->pluck('nombre')->values()->all(),
        ];
    }

    /** @return array{secretaria_ids:array<int,int>,nit_entidades:array<int,string>,faltantes:array<int,string>} */
    public function configuracionCompleta(): array
    {
        $scope = $this->configuracion();

        if ($scope['secretaria_ids'] === []) {
            throw ValidationException::withMessages([
                'entidades' => 'No hay entidades habilitadas para la auditoría SECOP departamental.',
            ]);
        }
        if ($scope['faltantes'] !== []) {
            $names = implode(', ', array_slice($scope['faltantes'], 0, 6));
            $remaining = count($scope['faltantes']) - min(6, count($scope['faltantes']));
            throw ValidationException::withMessages([
                'entidades' => 'Falta parametrizar el NIT SECOP de: '.$names.($remaining > 0 ? " y {$remaining} más." : '.'),
            ]);
        }

        return $scope;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
