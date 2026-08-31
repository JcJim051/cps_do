<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopActualizacionSeguimiento extends Model
{
    protected $table = 'secop_actualizaciones_seguimientos';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'campos_aplicados' => 'array',
            'campos_omitidos' => 'array',
            'origenes' => 'array',
            'alertas' => 'array',
            'aplicado_at' => 'datetime',
        ];
    }

    public function seguimiento() { return $this->belongsTo(Seguimiento::class); }
    public function vinculo() { return $this->belongsTo(SecopVinculo::class, 'vinculo_id'); }
    public function instantanea() { return $this->belongsTo(SecopInstantanea::class, 'instantanea_id'); }
}
