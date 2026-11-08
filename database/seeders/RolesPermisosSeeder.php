<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'usuarios.gestionar'       => 'Crear, editar y activar/desactivar usuarios',
            'roles.gestionar'          => 'Gestionar roles y asignar permisos',
            'auditoria.ver'            => 'Consultar el registro de auditoría',
            'sucursales.gestionar'     => 'Gestionar sucursales',
            'ubicaciones.gestionar'    => 'Gestionar ubicaciones (estante, nivel, casillero)',
            'catalogo.ver'             => 'Consultar el catálogo de productos',
            'catalogo.gestionar'       => 'Crear, editar y dar de baja productos',
            'stock.consultar'          => 'Consultar existencias por sucursal y ubicación',
            'stock_minimo.configurar'  => 'Configurar el stock mínimo por producto y sucursal',
            'alertas.ver'              => 'Ver alertas de stock mínimo',
            'entradas.registrar'       => 'Registrar entradas de inventario (compras)',
            'salidas.registrar'        => 'Registrar salidas por despacho o venta',
            'ajustes.registrar'        => 'Registrar ajustes, mermas y conteos',
            'transferencias.solicitar' => 'Solicitar transferencias entre sucursales',
            'transferencias.aprobar'   => 'Aprobar o rechazar transferencias',
            'transferencias.recibir'   => 'Confirmar la recepción de transferencias',
            'kardex.ver'               => 'Consultar el kardex de movimientos',
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permiso::create(['nombre_permiso' => $nombre, 'descripcion' => $descripcion]);
        }

        $roles = [
            Rol::ADMINISTRADOR => [
                'descripcion' => 'Acceso total: usuarios, roles, auditoría y configuración',
                'permisos'    => array_keys($permisos),
            ],
            Rol::ALMACEN => [
                'descripcion' => 'Operación de almacén: entradas, salidas, ajustes y transferencias',
                'permisos'    => [
                    'catalogo.ver', 'stock.consultar', 'alertas.ver', 'entradas.registrar',
                    'salidas.registrar', 'ajustes.registrar', 'transferencias.solicitar',
                    'transferencias.recibir', 'kardex.ver',
                ],
            ],
            Rol::VENTAS => [
                'descripcion' => 'Consulta de stock y registro de salidas por venta',
                'permisos'    => ['catalogo.ver', 'stock.consultar', 'alertas.ver', 'salidas.registrar'],
            ],
        ];

        foreach ($roles as $nombre => $datos) {
            $rol = Rol::create(['nombre_rol' => $nombre, 'descripcion' => $datos['descripcion']]);
            $ids = Permiso::whereIn('nombre_permiso', $datos['permisos'])->pluck('id_permiso');
            $rol->permisos()->sync($ids);
        }
    }
}
