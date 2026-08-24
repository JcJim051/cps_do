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
            'datos' => 'array',
            'consultado_at' => 'datetime',
        ];
    }
}
