<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    /** Un usuario por rol. Las credenciales están documentadas en el README. */
    public function run(): void
    {
        Usuario::factory()->administrador()->create([
            'nombre'        => 'Carlos',
            'apellido'      => 'Mendoza Ríos',
            'email'         => 'admin@inventario.test',
            'password_hash' => Hash::make('Admin#2026'),
        ]);

        Usuario::factory()->ventas()->create([
            'nombre'        => 'Lucía',
            'apellido'      => 'Fernández Torres',
            'email'         => 'ventas@inventario.test',
            'password_hash' => Hash::make('Ventas#2026'),
        ]);

        Usuario::factory()->almacen()->create([
            'nombre'        => 'Jorge',
            'apellido'      => 'Huanca Condori',
            'email'         => 'almacen@inventario.test',
            'password_hash' => Hash::make('Almacen#2026'),
        ]);
    }
}
