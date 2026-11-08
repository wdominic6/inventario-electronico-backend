<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposMovimientoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_movimiento')->insert([
            ['nombre_tipo' => 'Entrada Compra',    'factor' => 1],
            ['nombre_tipo' => 'Salida Venta',      'factor' => -1],
            ['nombre_tipo' => 'Ajuste Positivo',   'factor' => 1],
            ['nombre_tipo' => 'Ajuste Negativo',   'factor' => -1],
            ['nombre_tipo' => 'Traslado Entrante', 'factor' => 1],
            ['nombre_tipo' => 'Traslado Saliente', 'factor' => -1],
        ]);
    }
}
