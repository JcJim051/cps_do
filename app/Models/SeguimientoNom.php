<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class SeguimientoNom extends Model
{
    use CrudTrait;

    protected $table = 'seguimientos_nom';

    protected $fillable = [
        'persona_id',
        'secretaria_id',
        'cargo_id',
        'tipo_vinculacion_id',
        'fecha_ingreso',
        'salario',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'salario' => 'decimal:2',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function secretaria()
    {
        return $this->belongsTo(Secretaria::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function tipoVinculacion()
    {
        return $this->belongsTo(TipoVinculacion::class, 'tipo_vinculacion_id');
    }
}
