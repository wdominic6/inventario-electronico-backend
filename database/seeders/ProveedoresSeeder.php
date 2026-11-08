<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedoresSeeder extends Seeder
{
    /** Proveedores ficticios (RUC y datos de contacto inventados para la demo). */
    public function run(): void
    {
        $proveedores = [
            ['Electrónica Andina S.A.C.', '20000000001', 'Marco Salazar', '+51 954 000 101', 'ventas@electronicaandina.test', 'Av. Abancay 500, Lima'],
            ['Componentes del Sur E.I.R.L.', '20000000002', 'Rosa Chambi', '+51 954 000 102', 'contacto@componentesdelsur.test', 'Calle Mercaderes 210, Arequipa'],
            ['Importadora TecnoPerú S.A.C.', '20000000003', 'Diego Paredes', '+51 954 000 103', 'pedidos@tecnoperu.test', 'Jr. Quilca 330, Lima'],
        ];

        foreach ($proveedores as [$razon, $ruc, $contacto, $telefono, $email, $direccion]) {
            Proveedor::factory()->create([
                'razon_social' => $razon,
                'ruc'          => $ruc,
                'contacto'     => $contacto,
                'telefono'     => $telefono,
                'email'        => $email,
                'direccion'    => $direccion,
            ]);
        }
    }
}
