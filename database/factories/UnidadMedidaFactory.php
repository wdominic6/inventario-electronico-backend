<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnidadMedida> */
class UnidadMedidaFactory extends Factory
{
    protected $model = UnidadMedida::class;

    public function definition(): array
    {
        $unidad = fake()->unique()->randomElement([
            ['Unidad', 'und'], ['Paquete', 'paq'], ['Caja', 'caja'], ['Rollo', 'rollo'], ['Metro', 'm'],
        ]);

        return ['nombre' => $unidad[0], 'abreviatura' => $unidad[1]];
    }
}
