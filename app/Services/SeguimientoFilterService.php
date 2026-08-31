<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SeguimientoFilterService
{
    /** @return array<int, int> */
    public function ids(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        } elseif (! is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        return collect($value)
            ->flatten()
            ->map(fn ($item) => is_scalar($item) ? trim((string) $item) : '')
            ->filter(fn (string $item) => ctype_digit($item) && (int) $item > 0)
            ->map(fn (string $item) => (int) $item)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normaliza valores individuales y enlaces antiguos al formato JSON que
     * esperan los filtros múltiples de Backpack.
     *
     * @return array<int, int> IDs de personas seleccionadas
     */
    public function normalizeListRequest(Request $request): array
    {
        foreach (['filtro_referencia', 'estado_contrato_id', 'anio', 'secretaria_id', 'gerencia_id'] as $name) {
            if (! $request->query->has($name)) {
                continue;
            }

            $ids = $this->ids($request->query($name));
            $ids === []
                ? $request->query->remove($name)
                : $request->query->set($name, json_encode($ids));
        }

        $people = $this->personIds($request->query());
        if ($people !== []) {
            $request->query->set('personas', json_encode($people));
        } elseif ($request->query->has('personas')) {
            $request->query->remove('personas');
        }

        return $people;
    }

    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $this->applyWhereIn($query, 'persona_id', $this->personIds($filters));
        $this->applyWhereIn($query, 'estado_contrato_id', $this->ids($filters['estado_contrato_id'] ?? null));
        $this->applyWhereIn($query, 'anio', $this->ids($filters['anio'] ?? null));
        $this->applyWhereIn($query, 'secretaria_id', $this->ids($filters['secretaria_id'] ?? null));
        $this->applyWhereIn($query, 'gerencia_id', $this->ids($filters['gerencia_id'] ?? null));

        $references = $this->ids($filters['filtro_referencia'] ?? null);
        if ($references !== []) {
            $query->whereHas('persona.referencias', function (Builder $referenceQuery) use ($references) {
                $referenceQuery->whereIn('referencias.id', $references);
            });
        }

        $observations = trim((string) ($filters['observaciones'] ?? ''));
        if ($observations !== '') {
            $query->where(function (Builder $observationQuery) use ($observations) {
                $observationQuery
                    ->where('observaciones', 'like', "%{$observations}%")
                    ->orWhere('observaciones_contrato', 'like', "%{$observations}%");
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, int>
     */
    public function personIds(array $filters): array
    {
        $people = $this->ids($filters['personas'] ?? null);
        if ($people !== []) {
            return $people;
        }

        return array_values(array_unique(array_merge(
            $this->ids($filters['persona_id'] ?? null),
            $this->ids($filters['persona_cedula'] ?? null),
        )));
    }

    /** @param array<int, int> $values */
    private function applyWhereIn(Builder $query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
    }
}
