<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrevalidacionEvento extends Model
{
    protected $table = 'prevalidacion_eventos';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array'];
    }
}
