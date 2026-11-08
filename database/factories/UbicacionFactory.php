<?php

namespace Database\Factories;

use App\Models\Sucursal;
use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ojo: (sucursal, estante, nivel, casillero) es único. Con valores aleatorios pueden
 * chocar al crear muchas; para datos masivos pasa los valores explícitos (ver seeder).
 *
 * @extends Factory<Ubicacion>
 */
class UbicacionFactory extends Factory
{
    protected $model = Ubicacion::class;

    public function definition(): array
    {
        return [
            'id_sucursal' => Sucursal::factory(),
            'estante'     => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'nivel'       => (string) fake()->numberBetween(1, 4),
            'casillero'   => sprintf('%02d', fake()->numberBetween(1, 4)),
            'estado'      => 'disponible',
            'descripcion' => null,
        ];
    }
}
