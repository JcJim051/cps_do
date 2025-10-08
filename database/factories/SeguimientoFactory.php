<?php

namespace Database\Factories;

use App\Models\Seguimiento;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeguimientoFactory extends Factory
{
    protected $model = Seguimiento::class;

    public function definition()
    {
        return [
            'persona_id' => Persona::factory(),
            'tipo'       => $this->faker->randomElement(['entrevista', 'contrato']),
            'evaluacion_id' => null,
            'nivel_academico_id' => null,
            'estado_id' => null,
            'secretaria_id' => null,
            'gerencia_id' => null,
            'fuente_id' => null,
            'observaciones' => $this->faker->sentence(),
            // Campos de entrevista
            'fecha_entrevista' => $this->faker->optional()->date(),
            // Campos de contrato
            'anio' => $this->faker->optional()->year(),
            'numero_contrato' => $this->faker->optional()->numerify('C-####'),
            'fecha_acta_inicio' => $this->faker->optional()->date(),
            'fecha_finalizacion' => $this->faker->optional()->date(),
            'tiempo_ejecucion_dias' => $this->faker->optional()->numberBetween(30, 365),
            'valor_mensual' => $this->faker->optional()->randomFloat(2, 1000, 10000),
            'valor_total' => $this->faker->optional()->randomFloat(2, 10000, 100000),
            'estado_contrato_id'  => null,
            'aut_despacho' => $this->faker->boolean(),
            'fecha_aut_despacho' => $this->faker->optional()->date(),
            'aut_planeacion' => $this->faker->boolean(),
            'fecha_aut_planeacion' => $this->faker->optional()->date(),
            'aut_administrativa' => $this->faker->boolean(),
            'fecha_aut_administrativa' => $this->faker->optional()->date(),
            'adicion' => $this->faker->optional()->randomElement(['SI','NO']),
            'fecha_acta_inicio_adicion' => $this->faker->optional()->date(),
            'fecha_finalizacion_adicion' => $this->faker->optional()->date(),
            'tiempo_ejecucion_dias_adicion' => $this->faker->optional()->numberBetween(0,100),
            'tiempo_total_ejecucion_dias' => $this->faker->optional()->numberBetween(30,500),
            'valor_adicion' => $this->faker->optional()->randomFloat(2, 0, 5000),
            'valor_total_contrato' => $this->faker->optional()->randomFloat(2, 10000, 200000),
            'observaciones_contrato' => $this->faker->sentence(),
        ];
    }
}
