<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipoCampania extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'equipos_campania';

    protected $guarded = ['id'];

    protected $fillable = [
        'ejercicio_politico_id',
        'coordinador_user_id',
        'nombre',
    ];

    public function ejercicioPolitico()
    {
        return $this->belongsTo(EjercicioPolitico::class, 'ejercicio_politico_id');
    }

    public function coordinador()
    {
        return $this->belongsTo(User::class, 'coordinador_user_id');
    }

    public function personas()
    {
        return $this->belongsToMany(Persona::class, 'equipo_campania_persona', 'equipo_campania_id', 'persona_id')
            ->withPivot(['priorizacion']);
    }
}
