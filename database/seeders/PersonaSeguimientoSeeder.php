<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Persona;
use App\Models\Seguimiento;

class PersonaSeguimientoSeeder extends Seeder
{
    public function run()
    {
        // Crear 10 personas
        Persona::factory(10)->create()->each(function ($persona) {
            // Crear 1 a 3 seguimientos por persona
            Seguimiento::factory(rand(1,3))->create([
                'persona_id' => $persona->id,
            ]);
        });
    }
}

