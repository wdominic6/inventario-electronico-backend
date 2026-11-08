<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial: por cada sucursal una entrada de compra "Completada" con su detalle, las
 * existencias (stocks), el kardex (stock_anterior = 0) y los stock mínimos por sucursal.
 * Deja 5 pares producto-sucursal en alerta de stock mínimo para probar RF07.
 */
class InventarioInicialSeeder extends Seeder
{
    /** Estante donde se guarda cada categoría (mismo plano en las tres sucursales). */
    private const ESTANTE_POR_CATEGORIA = [
        'Resistencias' => 'A', 'Condensadores' => 'A',
        'Semiconductores' => 'B', 'Circuitos integrados' => 'B',
        'Microcontroladores' => 'C', 'Placas de desarrollo' => 'C',
        'Sensores' => 'D',
        'Módulos y displays' => 'E', 'Prototipado' => 'E',
    ];

    /** categoría => [stock base en la sede central, stock mínimo] */
    private const BASE_POR_CATEGORIA = [
        'Resistencias' => [500, 100], 'Condensadores' => [300, 80],
        'Semiconductores' => [200, 50], 'Circuitos integrados' => [100, 30],
        'Microcontroladores' => [40, 10], 'Placas de desarrollo' => [25, 8],
        'Sensores' => [40, 10], 'Módulos y displays' => [30, 8], 'Prototipado' => [50, 10],
    ];

    /** Proporción del stock base según el orden de la sucursal (Puno, Juliaca, Arequipa). */
    private const FACTOR_SUCURSAL = [1.0, 0.6, 0.5];

    private const STOCK_MINIMO_POR_CODIGO = ['PLA-RPI4-4GB' => 5];

    /** código => [índice de sucursal => cantidad]; null = sin existencias (no hay fila en stocks). */
    private const EXCEPCIONES = [
        'RES-CF-10K'        => [1 => 80],   // Juliaca: 80 <= 100  -> alerta
        'PLA-RPI4-4GB'      => [1 => 2],    // Juliaca: 2 <= 5     -> alerta
        'MCU-ESP32-WROOM32' => [2 => 10],   // Arequipa: 10 <= 10  -> alerta (igual al mínimo)
        'SEN-MQ2'           => [2 => 6],    // Arequipa: 6 <= 10   -> alerta
        'MOD-RELE-5V-1CH'   => [2 => null], // Arequipa: sin stock -> alerta
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $almacenista = Usuario::whereHas('rol', fn ($q) => $q->where('nombre_rol', Rol::ALMACEN))
                ->value('id_usuario');
            $tipoEntrada = DB::table('tipos_movimiento')->where('nombre_tipo', 'Entrada Compra')->value('id_tipo_movimiento');
            $proveedores = DB::table('proveedores')->orderBy('id_proveedor')->pluck('id_proveedor')->all();
            $sucursales = DB::table('sucursales')->orderBy('id_sucursal')->get();
            $productos = DB::table('productos as p')
                ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
                ->orderBy('p.id_producto')
                ->get(['p.id_producto', 'p.codigo', 'c.nombre_categoria']);

            // Casillero asignado a cada producto: se llena cada estante por orden de alta.
            $slots = [];
            $contador = [];
            foreach ($productos as $p) {
                $estante = self::ESTANTE_POR_CATEGORIA[$p->nombre_categoria];
                $i = $contador[$estante] = ($contador[$estante] ?? -1) + 1;
                $slots[$p->id_producto] = [$estante, (string) (intdiv($i, 4) + 1), sprintf('%02d', $i % 4 + 1)];
            }

            foreach ($sucursales as $idx => $sucursal) {
                $ubicaciones = DB::table('ubicaciones')
                    ->where('id_sucursal', $sucursal->id_sucursal)
                    ->get()
                    ->mapWithKeys(fn ($u) => ["{$u->estante}|{$u->nivel}|{$u->casillero}" => $u->id_ubicacion]);

                $idEntrada = DB::table('entradas_inventario')->insertGetId([
                    'codigo_entrada' => sprintf('ENT-2026-%04d', $idx + 1),
                    'id_sucursal'    => $sucursal->id_sucursal,
                    'id_proveedor'   => $proveedores[$idx % count($proveedores)],
                    'id_usuario'     => $almacenista,
                    'fecha_entrada'  => now(),
                    'estado'         => 'Completado',
                    'observacion'    => 'Carga inicial de inventario (datos de demostración)',
                ]);

                $minimos = $detalles = $stocks = $kardex = [];

                foreach ($productos as $p) {
                    [$base, $minimo] = self::BASE_POR_CATEGORIA[$p->nombre_categoria];
                    $minimo = self::STOCK_MINIMO_POR_CODIGO[$p->codigo] ?? $minimo;

                    $minimos[] = [
                        'id_producto' => $p->id_producto,
                        'id_sucursal' => $sucursal->id_sucursal,
                        'stock_minimo' => $minimo,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];

                    $excepciones = self::EXCEPCIONES[$p->codigo] ?? [];
                    $cantidad = array_key_exists($idx, $excepciones)
                        ? $excepciones[$idx]
                        : (int) round($base * self::FACTOR_SUCURSAL[$idx]);

                    if (! $cantidad) {
                        continue; // sin existencias: tiene mínimo configurado pero no fila en stocks
                    }

                    $ubicacion = $ubicaciones[implode('|', $slots[$p->id_producto])];

                    $detalles[] = [
                        'id_entrada'     => $idEntrada,
                        'id_producto'    => $p->id_producto,
                        'id_ubicacion'   => $ubicacion,
                        'cantidad'       => $cantidad,
                        'costo_unitario' => null,
                    ];

                    $stocks[] = [
                        'id_producto'  => $p->id_producto,
                        'id_sucursal'  => $sucursal->id_sucursal,
                        'id_ubicacion' => $ubicacion,
                        'cantidad'     => $cantidad,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];

                    $kardex[] = [
                        'id_producto'        => $p->id_producto,
                        'id_sucursal'        => $sucursal->id_sucursal,
                        'id_ubicacion'       => $ubicacion,
                        'id_usuario'         => $almacenista,
                        'id_tipo_movimiento' => $tipoEntrada,
                        'id_entrada'         => $idEntrada,
                        'id_salida'          => null,
                        'id_ajuste'          => null,
                        'id_transferencia'   => null,
                        'cantidad'           => $cantidad,
                        'stock_anterior'     => 0,
                        'stock_resultante'   => $cantidad,
                        'observacion'        => 'Carga inicial de inventario',
                        'fecha_hora'         => now(),
                    ];
                }

                DB::table('stock_minimos')->insert($minimos);
                DB::table('detalles_entrada')->insert($detalles);
                DB::table('stocks')->insert($stocks);
                DB::table('movimientos_kardex')->insert($kardex);
            }
        });
    }
}
