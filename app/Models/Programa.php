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
        'external_id',
        'operador',
        'perfil',
        'municipio',
        'institucion',
        'sede',
        'nombre_apellidos',
        'cedula',
        'contacto',
        'ref_1',
        'ref_2',
        'estado',
        'fila_origen',
        'origen',
        'presente_en_origen',
        'hash_origen',
        'ultima_sincronizacion_at',
        'foto',
    ];

    protected function casts(): array
    {
        return [
            'presente_en_origen' => 'boolean',
            'fila_origen' => 'integer',
            'ultima_sincronizacion_at' => 'datetime',
        ];
    }

      // 🔥 ESTO ES OBLIGATORIO PARA UPLOAD
      public function setFotoAttribute($value)
      {
          $attribute_name = "foto";
          $disk = "public";
          $destination_path = "programas";
  
          $this->uploadFileToDisk($value, $attribute_name, $disk, $destination_path);
      }
}
