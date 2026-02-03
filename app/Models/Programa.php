<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Support\Facades\Auth;

class Programa extends Model
{ 
    use CrudTrait;

    protected $table = 'programas';

    protected $fillable = [
        'operador',
        'municipio',
        'institucion',
        'sede',
        'nombre_apellidos',
        'cedula',
        'contacto',
        'ref_1',
        'ref_2',
        'foto',
    ];

      // 🔥 ESTO ES OBLIGATORIO PARA UPLOAD
      public function setFotoAttribute($value)
      {
          $attribute_name = "foto";
          $disk = "public";
          $destination_path = "programas";
  
          $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);
      }
}
