<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Requiere que los roles ya existan (RolesPermisosSeeder).
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        $faker = fake('es_ES');

        return [
            'id_rol'        => fn () => Rol::where('nombre_rol', Rol::ALMACEN)->value('id_rol'),
            'nombre'        => $faker->firstName(),
            'apellido'      => $faker->lastName() . ' ' . $faker->lastName(),
            'email'         => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'estado'        => 'activo',
        ];
    }

    public function administrador(): static
    {
        return $this->conRol(Rol::ADMINISTRADOR);
    }

    public function ventas(): static
    {
        return $this->conRol(Rol::VENTAS);
    }

    public function almacen(): static
    {
        return $this->conRol(Rol::ALMACEN);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => 'inactivo']);
    }

    private function conRol(string $nombreRol): static
    {
        return $this->state(fn () => [
            'id_rol' => Rol::where('nombre_rol', $nombreRol)->value('id_rol'),
        ]);
    }
}
