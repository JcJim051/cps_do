<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopConciliacionLote extends Model
{
    protected $table = 'secop_conciliacion_lotes';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->estado, ['pendiente', 'procesando'], true);
    }
}
