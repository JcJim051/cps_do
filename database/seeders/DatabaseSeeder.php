<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolesAndUsersSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(MaestroSeeder::class);
        $this->call(PersonaSeguimientoSeeder::class);

       
    }
}
