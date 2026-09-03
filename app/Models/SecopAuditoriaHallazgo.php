<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopAuditoriaHallazgo extends Model
{
    protected $table = 'secop_auditoria_hallazgos';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_firma' => 'date',
            'valor_total' => 'decimal:2',
            'detalle' => 'array',
        ];
    }

    public function persona() { return $this->belongsTo(Persona::class); }
    public function seguimiento() { return $this->belongsTo(Seguimiento::class); }
    public function lote() { return $this->belongsTo(SecopAuditoriaLote::class, 'lote_id'); }
}
