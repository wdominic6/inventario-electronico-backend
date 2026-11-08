<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Fabricante;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera componentes electrónicos reales (nombre, modelo, encapsulado, voltaje y
 * tolerancia coherentes). El catálogo curado de la demo está en CatalogoSeeder.
 *
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    // [nombre, modelo, encapsulado, voltaje_operacion, tolerancia]
    private const COMPONENTES = [
        ['Amplificador operacional doble LM358', 'LM358N', 'DIP-8', '3-32V', null],
        ['Temporizador NE555', 'NE555P', 'DIP-8', '4.5-16V', null],
        ['Regulador de voltaje 5 V L7805CV', 'L7805CV', 'TO-220', '7-35V (entrada)', null],
        ['Diodo rectificador 1N4007', '1N4007', 'DO-41', '1000V (inversa)', null],
        ['Transistor NPN BC547B', 'BC547B', 'TO-92', '45V (Vceo)', null],
        ['Registro de desplazamiento 74HC595', 'SN74HC595N', 'DIP-16', '2-6V', null],
        ['Driver de relés ULN2003A', 'ULN2003A', 'DIP-16', '50V (salida)', null],
        ['Resistencia de película de carbono 330 Ω 1/4 W', 'CFR-25JB-52-330R', 'Axial THT', null, '±5%'],
        ['Resistencia de película de carbono 2.2 kΩ 1/4 W', 'CFR-25JB-52-2K2', 'Axial THT', null, '±5%'],
        ['Capacitor electrolítico 470 µF 16 V', null, 'Radial THT', '16V', '±20%'],
        ['Capacitor cerámico 22 pF 50 V', null, 'Disco THT', '50V', '±5%'],
        ['LED 5 mm verde', null, '5 mm THT', '2.2V (directa)', null],
    ];

    public function definition(): array
    {
        [$nombre, $modelo, $encapsulado, $voltaje, $tolerancia] = fake()->randomElement(self::COMPONENTES);

        return [
            'id_categoria'          => Categoria::factory(),
            'id_fabricante'         => Fabricante::factory(),
            'id_unidad'             => UnidadMedida::factory(),
            'codigo'                => 'GEN-' . strtoupper(fake()->unique()->bothify('????-###')),
            'nombre'                => $nombre,
            'modelo'                => $modelo,
            'encapsulado'           => $encapsulado,
            'voltaje_operacion'     => $voltaje,
            'tolerancia'            => $tolerancia,
            'especificaciones_extra' => null,
            'estado'                => 'activo',
        ];
    }

    public function descontinuado(): static
    {
        return $this->state(fn () => ['estado' => 'descontinuado']);
    }
}
