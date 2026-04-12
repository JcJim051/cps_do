<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Cargo extends Model
{
    use CrudTrait;

    protected $table = 'cargos';

    protected $fillable = [
        'nombre',
    ];
}
