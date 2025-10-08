<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    public function definition()
    {
        // URLs de ejemplo
        $fotoUrl = 'https://images.unsplash.com/photo-1511367461989-f85a21fda167?q=80&w=1931&auto=format&fit=crop';
        $pdfUrl  = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf';

        // Guardar la foto en storage/app/public/personas/fotos
        $fotoPath = 'personas/fotos/' . $this->faker->uuid() . '.jpg';
        Storage::disk('public')->put($fotoPath, file_get_contents($fotoUrl));

        // Guardar el PDF en storage/app/public/personas/docs
        $pdfPath = 'personas/docs/' . $this->faker->uuid() . '.pdf';
        Storage::disk('public')->put($pdfPath, file_get_contents($pdfUrl));

        return [
            'nombre_contratista' => $this->faker->name(),
            'cedula_o_nit'       => $this->faker->unique()->numerify('#########'),
            'celular'            => $this->faker->phoneNumber(),
            'genero'             => $this->faker->randomElement(['Masculino','Femenino']),
            'nivel_academico_id' => null, // o puedes asignar aleatorio de niveles existentes
            'tecnico_tecnologo_profesion' => $this->faker->jobTitle(),
            'especializacion'    => $this->faker->word(),
            'maestria'           => $this->faker->word(),
            'referencia_id'      => null, // o aleatorio de referencias
            'estado_persona_id'  => null,
            'foto'               => $fotoPath,
            'documento_pdf'      => $pdfPath,
        ];
    }
}
