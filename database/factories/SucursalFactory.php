<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sucursal> */
class SucursalFactory extends Factory
{
    protected $model = Sucursal::class;

    public function definition(): array
    {
        $ciudad = fake()->randomElement(['Puno', 'Juliaca', 'Arequipa', 'Cusco', 'Tacna', 'Moquegua']);

        return [
            'nombre_sucursal' => 'Sucursal ' . $ciudad,
            'direccion'       => 'Av. ' . fake('es_ES')->lastName() . ' ' . fake()->numberBetween(100, 2000) . ', ' . $ciudad,
            'telefono'        => fake()->numerify('+51 9## ### ###'),
            'estado'          => 'activa',
        ];
    }
}
