<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrevalidacionFuente extends Model
{
    protected $table = 'prevalidacion_fuentes';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mapeo_columnas' => 'array',
            'activa' => 'boolean',
            'anio_objetivo' => 'integer',
            'ultima_sincronizacion_at' => 'datetime',
        ];
    }

    public function secretaria()
    {
        return $this->belongsTo(Secretaria::class);
    }

    public function prevalidaciones()
    {
        return $this->hasMany(PrevalidacionContractual::class, 'fuente_id');
    }
}
