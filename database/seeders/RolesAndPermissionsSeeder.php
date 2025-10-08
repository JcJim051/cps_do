<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // 1️⃣ Crear permisos
        $permissions = ['crear', 'editar', 'ver', 'borrar'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2️⃣ Crear roles y asignar permisos
        $roles = [
            'admin' => ['crear', 'editar', 'ver', 'borrar'],
            'diana' => ['crear', 'editar', 'ver', 'borrar'],
            'secretario' => ['crear', 'editar', 'ver'],
            'gerente' => ['crear', 'editar', 'ver'],
            'coordinador' => ['crear', 'editar', 'ver'],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }
    }
}
