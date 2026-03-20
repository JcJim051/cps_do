<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EjercicioPolitico extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'ejercicios_politicos';

    protected $guarded = ['id'];

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function equiposCampania()
    {
        return $this->hasMany(EquipoCampania::class, 'ejercicio_politico_id');
    }

    public function personas()
    {
        return $this->belongsToMany(Persona::class, 'ejercicio_politico_persona', 'ejercicio_politico_id', 'persona_id');
    }
}
