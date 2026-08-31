<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopVinculo extends Model
{
    protected $table = 'secop_vinculos';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'vinculado_at' => 'datetime',
            'ultima_consulta_at' => 'datetime',
            'sincronizacion_automatica' => 'boolean',
            'campos_excluidos' => 'array',
            'origenes_campos' => 'array',
            'ultima_aplicacion_at' => 'datetime',
            'ultimo_resultado' => 'array',
        ];
    }

    public function prevalidacion()
    {
        return $this->belongsTo(PrevalidacionContractual::class, 'prevalidacion_id');
    }

    public function seguimiento()
    {
        return $this->belongsTo(Seguimiento::class);
    }

    public function instantaneas()
    {
        return $this->hasMany(SecopInstantanea::class, 'vinculo_id');
    }

    public function ultimaInstantanea()
    {
        return $this->hasOne(SecopInstantanea::class, 'vinculo_id')->latestOfMany('consultado_at');
    }

    public function actualizaciones()
    {
        return $this->hasMany(SecopActualizacionSeguimiento::class, 'vinculo_id');
    }
}
