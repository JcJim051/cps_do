<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class TipoVinculacion extends Model
{
    use CrudTrait;

    protected $table = 'tipos_vinculacion';

    protected $fillable = [
        'nombre',
    ];
}
