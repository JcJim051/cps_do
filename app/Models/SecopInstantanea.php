<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopInstantanea extends Model
{
    protected $table = 'secop_instantaneas';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_firma' => 'date',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'duracion_inicial' => 'decimal:2',
            'meses_adicionados' => 'decimal:2',
            'marcacion_adicion' => 'boolean',
            'ultima_actualizacion_fuente' => 'datetime',
            'datos' => 'array',
            'consultado_at' => 'datetime',
        ];
    }

    public function vinculo() { return $this->belongsTo(SecopVinculo::class, 'vinculo_id'); }
}
