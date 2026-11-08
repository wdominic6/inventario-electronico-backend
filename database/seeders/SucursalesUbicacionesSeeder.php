<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

class SucursalesUbicacionesSeeder extends Seeder
{
    public function run(): void
    {
        $sucursales = [
            ['Sede Central Puno', 'Jr. Lima 450, Puno', '+51 951 000 001'],
            ['Sucursal Juliaca', 'Av. Independencia 1200, Juliaca', '+51 951 000 002'],
            ['Sucursal Arequipa', 'Av. Ejército 800, Arequipa', '+51 951 000 003'],
        ];

        // Misma distribución física en las tres sucursales: 5 estantes x 4 niveles x 4 casilleros.
        $estantes = [
            'A' => 'Componentes pasivos (resistencias y condensadores)',
            'B' => 'Semiconductores y circuitos integrados',
            'C' => 'Microcontroladores y placas de desarrollo',
            'D' => 'Sensores',
            'E' => 'Módulos, displays y material de prototipado',
        ];

        foreach ($sucursales as [$nombre, $direccion, $telefono]) {
            $sucursal = Sucursal::factory()->create([
                'nombre_sucursal' => $nombre,
                'direccion'       => $direccion,
                'telefono'        => $telefono,
            ]);

            foreach ($estantes as $estante => $descripcion) {
                for ($nivel = 1; $nivel <= 4; $nivel++) {
                    for ($casillero = 1; $casillero <= 4; $casillero++) {
                        Ubicacion::factory()->create([
                            'id_sucursal' => $sucursal->id_sucursal,
                            'estante'     => $estante,
                            'nivel'       => (string) $nivel,
                            'casillero'   => sprintf('%02d', $casillero),
                            'descripcion' => $descripcion,
                        ]);
                    }
                }
            }
        }
    }
}
