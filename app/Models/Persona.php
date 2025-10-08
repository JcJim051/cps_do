<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Persona extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'personas';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
        'nombre_contratista',
        'cedula_o_nit',
        'celular',
        'genero',
        'nivel_academico_id',
        'tecnico_tecnologo_profesion',
        'especializacion',
        'maestria',
        'referencia_id',
        'estado_persona_id', // ¡Nuevo!
        'tipos_id',           // ¡Nuevo!
        'foto',
        'documento_pdf',
        'caso_id',
        'no_tocar',
        'secretaria_id',
        'gerencia_id',
    ];
    // protected $hidden = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function estadoPersona()
    {
        return $this->belongsTo(EstadoPersona::class, 'estado_persona_id');
    }
    
    // Relación para el campo 'tipos_id'
    public function tipo()
    {
        // Nota: Asumiendo que has nombrado el modelo 'Tipo'
        return $this->belongsTo(Tipo::class, 'tipos_id'); 
    }

    public function nivelAcademico()
    {
        return $this->belongsTo(NivelAcademico::class, 'nivel_academico_id');
    }

    // public function referencia()
    // {
    //     return $this->belongsTo(Referencia::class, 'referencia_id');
    // }
    public function referencias()
    {
        // Usa 'referencias' en plural. Laravel buscará la tabla 'persona_referencia' por convención.
        return $this->belongsToMany(Referencia::class, 'persona_referencia', 'persona_id', 'referencia_id');
    }

     public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function seguimientos()
    {
        return $this->hasMany(Seguimiento::class);
    }

    // Accessors: URLs públicos usando el disk "public"
    public function getFotoUrlAttribute()
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    public function getDocumentoPdfUrlAttribute()
    {
        return $this->documento_pdf ? Storage::disk('public')->url($this->documento_pdf) : null;
    }

    public function secretaria()
    {
        return $this->belongsTo(Secretaria::class);
    }

    public function gerencia()
    {
        return $this->belongsTo(Gerencia::class);
}

    // Eliminar archivos al borrar registro
    protected static function booted()
    {
        static::deleting(function (self $model) {
            if (!empty($model->foto)) {
                Storage::disk('public')->delete($model->foto);
            }
            if (!empty($model->documento_pdf)) {
                Storage::disk('public')->delete($model->documento_pdf);
            }
        });
    }


        
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
 

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
        public function setFotoAttribute($value)
    {
        if (is_file($value)) {
            // Elimina foto anterior si existe
            if (!empty($this->foto)) {
                Storage::disk('public')->delete($this->foto);
            }
            // Guarda la nueva
            $this->attributes['foto'] = $value->store('personas/fotos', 'public');
        } elseif (is_null($value)) {
            if (!empty($this->foto)) {
                Storage::disk('public')->delete($this->foto);
            }
            $this->attributes['foto'] = null;
        } else {
            // En caso de recibir URL o string ya existente, lo dejamos tal cual
            $this->attributes['foto'] = $value;
        }
    }

    public function setDocumentoPdfAttribute($value)
    {
        if (is_file($value)) {
            if (!empty($this->documento_pdf)) {
                Storage::disk('public')->delete($this->documento_pdf);
            }
            $this->attributes['documento_pdf'] = $value->store('personas/docs', 'public');
        } elseif (is_null($value)) {
            if (!empty($this->documento_pdf)) {
                Storage::disk('public')->delete($this->documento_pdf);
            }
            $this->attributes['documento_pdf'] = null;
        } else {
            $this->attributes['documento_pdf'] = $value;
        }
    }

}
