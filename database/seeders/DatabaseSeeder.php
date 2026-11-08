<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate:fresh --seed
     * El orden importa: cada seeder depende de los anteriores.
     */
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            UsuariosSeeder::class,
            SucursalesUbicacionesSeeder::class,
            ProveedoresSeeder::class,
            TiposMovimientoSeeder::class,
            CatalogoSeeder::class,
            InventarioInicialSeeder::class,
        ]);
    }
}
