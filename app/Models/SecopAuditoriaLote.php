<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopAuditoriaLote extends Model
{
    protected $table = 'secop_auditoria_lotes';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'persona_ids' => 'array',
            'secretaria_ids' => 'array',
            'nit_entidades' => 'array',
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->estado, ['pendiente', 'procesando'], true);
    }

    public function hallazgos()
    {
        return $this->hasMany(SecopAuditoriaHallazgo::class, 'lote_id');
    }

    public function getCriterioVigenciaLabelAttribute(): string
    {
        $vigencia = $this->criterio_vigencia === 'ejecucion_superpuesta'
            ? 'Ejecución activa durante '.$this->anio
            : 'Firma desde '.$this->anio;

        $personas = $this->filtro_persona
            ? $vigencia.' · Búsqueda: '.$this->filtro_persona
            : $vigencia.' · Todas las personas';

        $entidades = $this->alcance_entidad === 'departamental_parametrizado'
            ? 'Solo entidades departamentales parametrizadas'
            : 'Sin filtro de entidad (histórico)';

        return $personas.' · '.$entidades;
    }

    /** @return array<string,array{personas:int,registros:int}> */
    public function metricasPorTipo(): array
    {
        $types = ['solo_integra', 'solo_secop', 'requiere_revision', 'coincidencia_exacta', 'alerta_secop'];
        $metrics = collect($types)->mapWithKeys(fn ($type) => [$type => ['personas' => 0, 'registros' => 0]])->all();

        $this->hallazgos()
            ->selectRaw('tipo, COUNT(*) AS registros, COUNT(DISTINCT persona_id) AS personas')
            ->groupBy('tipo')
            ->get()
            ->each(function ($row) use (&$metrics) {
                if (isset($metrics[$row->tipo])) {
                    $metrics[$row->tipo] = ['personas' => (int) $row->personas, 'registros' => (int) $row->registros];
                }
            });

        return $metrics;
    }
}
