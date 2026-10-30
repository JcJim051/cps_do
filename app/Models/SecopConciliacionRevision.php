<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecopConciliacionRevision extends Model
{
    protected $table = 'secop_conciliacion_revisiones';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'razones' => 'array',
            'bloqueos' => 'array',
            'candidato' => 'array',
            'resuelto_at' => 'datetime',
        ];
    }

    public function lote() { return $this->belongsTo(SecopConciliacionLote::class, 'lote_id'); }
    public function seguimiento() { return $this->belongsTo(Seguimiento::class); }
    public function resueltoPor() { return $this->belongsTo(User::class, 'resuelto_por'); }
}
