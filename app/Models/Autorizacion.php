<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Autorizacion extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'seguimientos';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'aut_despacho' => 'boolean',
        'aut_planeacion' => 'boolean',
        'aut_administrativa' => 'boolean',
        'fecha_aut_despacho' => 'date',
        'fecha_aut_planeacion' => 'date',
        'fecha_aut_administrativa' => 'date',
    ];

    protected $fillable = [
        'persona_id', 'secretaria_id', 'gerencia_id',
        'aut_despacho', 'aut_planeacion', 'aut_administrativa',
        'fecha_aut_despacho', 'fecha_aut_planeacion', 'fecha_aut_administrativa','estado_aprobacion',
    ];
    // protected $fillable = [];
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
// Relación con secretaria
    public function secretaria()
    {
        return $this->belongsTo(\App\Models\Secretaria::class, 'secretaria_id');
    }

    // Relación con persona
    public function persona()
    {
        return $this->belongsTo(\App\Models\Persona::class, 'persona_id');
    }

    // Relación con gerencia si la necesitas
    public function gerencia()
    {
        return $this->belongsTo(\App\Models\Gerencia::class, 'gerencia_id');
    }   

        public function getAutDespachoIcon()
    {
        return $this->aut_despacho 
            ? '<span style="color:green;">✔</span>' 
            : '<span style="color:red;">✖</span>';
    }

    public function getAutPlaneacionIcon()
    {
        return $this->aut_planeacion 
            ? '<span style="color:green;">✔</span>' 
            : '<span style="color:red;">✖</span>';
    }

    public function getAutAdministrativaIcon()
    {
        return $this->aut_administrativa 
            ? '<span style="color:green;">✔</span>' 
            : '<span style="color:red;">✖</span>';
    }
    public function fuente()
        {
            return $this->belongsTo(Fuente::class);
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
    public function getEstadoAprobacionShort()
    {
        $map = [
            'mayor' => 'Mayor',
            'menor' => 'Menor',
            'sin'   => 'Sin',
        ];
    
        return $map[$this->estado_aprobacion] ?? $this->estado_aprobacion;
    }
    public function getNombreResaltadoTooltip(): string
    {
        $nombre = $this->persona->nombre_contratista ?? 'N/A';

        if ($this->estado_aprobacion !== null) {
            $map = [
                'mayor' => 'Mayor valor al aprobado',
                'menor' => 'Menor valor al aprobado',
                'sin'   => 'Sin aprobación',
            ];

            $label = [
                'mayor' => 'Mayor',
                'menor' => 'Menor',
                'sin'   => 'Sin',
            ][$this->estado_aprobacion] ?? ucfirst($this->estado_aprobacion);

            $tooltip = $map[$this->estado_aprobacion] ?? $this->estado_aprobacion;

            return '<span style="color:#6a0dad; font-weight:600;" data-bs-toggle="tooltip" title="'.$tooltip.'">'
                    .e($nombre).
                    '</span>
                    <span class="badge ms-1" style="background-color:#6a0dad;" data-bs-toggle="tooltip" title="'.$tooltip.'">'
                    .$label.
                    '</span>';
        }

        return e($nombre);
    }

}
