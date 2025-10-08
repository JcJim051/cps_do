<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Referencia;



class User extends Authenticatable
{
    use CrudTrait;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',          // id del rol
        'referencia_id', // relación con referencia
        'secretaria_id',
        'gerencia_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function roleRelation()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }

    public function referencia()
    {
        return $this->belongsTo(Referencia::class);
    }
     
        // app/Models/User.php

    public function secretaria()
    {
        return $this->belongsTo(\App\Models\Secretaria::class, 'secretaria_id');
    }

    public function gerencia()
    {
        return $this->belongsTo(\App\Models\Gerencia::class, 'gerencia_id');
    }

    protected static function booted()
    {
        static::retrieved(function ($user) {
            if ($user->role_id) {
                // Busca el nombre del rol según tu lógica
                $role = \Spatie\Permission\Models\Role::find($user->role_id);
    
                if ($role) {
                    // Reemplaza todos los roles previos por este
                    $user->syncRoles([$role->name]);
                }
            } else {
                // Si no tiene role_id, se limpian los roles
                $user->syncRoles([]);
            }
        });
    }
    
}
