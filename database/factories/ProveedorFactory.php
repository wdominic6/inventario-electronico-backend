<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Proveedor> */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        $faker = fake('es_ES');

        return [
            'razon_social' => $faker->company(),
            'ruc'          => '20' . fake()->unique()->numerify('#########'),
            'contacto'     => $faker->firstName() . ' ' . $faker->lastName(),
            'telefono'     => fake()->numerify('+51 9## ### ###'),
            'email'        => fake()->unique()->companyEmail(),
            'direccion'    => 'Av. ' . $faker->lastName() . ' ' . fake()->numberBetween(100, 2000) . ', Lima',
            'estado'       => 'activo',
        ];
    }
}
