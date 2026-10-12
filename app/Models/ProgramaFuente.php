<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaFuente extends Model
{
    protected $table = 'programa_fuentes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'fila_encabezados' => 'integer',
            'ultima_sincronizacion_at' => 'datetime',
            'ultimo_resumen' => 'array',
        ];
    }
}
