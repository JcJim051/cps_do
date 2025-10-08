<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndUsersSeeder extends Seeder
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
            'bancos' => ['crear', 'editar', 'ver'],
            'Administrativa' => ['crear', 'editar', 'ver'],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }

        // 3️⃣ Crear usuarios por rol
        $users = [
            ['name' => 'Joni', 'email' => 'joni051@hotmail.com', 'password' => 'Alejandra20', 'role' => 'admin', 'role_id' => '1'],
            ['name' => 'Diana', 'email' => 'diana@example.com', 'password' => 'Alejandra20', 'role' => 'Diana', 'role_id'=>'2'],
            ['name' => 'Secretario', 'email' => 'secretario@example.com', 'password' => 'Alejandra20', 'role' => 'secretario', 'role_id'=> '3'],
            ['name' => 'Gerente', 'email' => 'gerente@example.com', 'password' => 'Alejandra20', 'role' => 'gerente', 'role_id'=> '4'],
            ['name' => 'Coordinador', 'email' => 'coordinador@example.com', 'password' => 'Alejandra20', 'role' => 'coordinador', 'role_id'=> '5'],
            ['name' => 'Bancos', 'email' => 'bancos@example.com', 'password' => 'Alejandra20', 'role' => 'bancos', 'role_id'=> '6'],
            ['name' => 'Administrativa', 'email' => 'administrativa@example.com', 'password' => 'Alejandra20', 'role' => 'Administrativa', 'role_id'=>'7'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'role_id' => $u['role_id'],   
                
                ]
            );
            $user->syncRoles([$u['role']]);
        }
    }
}
