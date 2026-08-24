<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrevalidacionContractual extends Model
{
    public const ESTADOS = ['PENDIENTE', 'CAMBIO', 'APROBADO'];
    public const ETAPAS = ['POR_GESTIONAR', 'APROBADO_POR_ENVIAR', 'EN_SEGUIMIENTO', 'HISTORIAL'];

    protected $table = 'prevalidaciones_contractuales';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_inicio_planeada' => 'date',
            'fecha_fin_planeada' => 'date',
            'fecha_inicio_adicion' => 'date',
            'fecha_fin_adicion' => 'date',
            'datos_origen' => 'array',
            'campos_locales' => 'array',
            'conflictos' => 'array',
            'estado_gestionado_localmente' => 'boolean',
            'editado_en_integra' => 'boolean',
            'presente_en_origen' => 'boolean',
            'nombre_requiere_revision' => 'boolean',
            'aprobado_at' => 'datetime',
            'promovido_at' => 'datetime',
            'drive_protegido_at' => 'datetime',
            'drive_sincronizacion_pendiente' => 'boolean',
            'ultima_sincronizacion_at' => 'datetime',
        ];
    }

    public function fuente()
    {
        return $this->belongsTo(PrevalidacionFuente::class, 'fuente_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function secretaria()
    {
        return $this->belongsTo(Secretaria::class);
    }

    public function gerencia()
    {
        return $this->belongsTo(Gerencia::class);
    }

    public function seguimiento()
    {
        return $this->belongsTo(Seguimiento::class);
    }

    public function vinculoSecop()
    {
        return $this->hasOne(SecopVinculo::class, 'prevalidacion_id');
    }

    public function eventos()
    {
        return $this->hasMany(PrevalidacionEvento::class, 'prevalidacion_id');
    }

    public function getEsPersonaNuevaAttribute(): bool
    {
        return $this->persona_id === null;
    }
}
