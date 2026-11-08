<?php

namespace Database\Factories;

use App\Models\Fabricante;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fabricante> */
class FabricanteFactory extends Factory
{
    protected $model = Fabricante::class;

    private const FABRICANTES = [
        'Texas Instruments' => 'EE. UU.',
        'STMicroelectronics' => 'Suiza',
        'Microchip' => 'EE. UU.',
        'Espressif' => 'China',
        'Infineon' => 'Alemania',
        'Vishay' => 'EE. UU.',
        'Yageo' => 'Taiwán',
        'Bosch Sensortec' => 'Alemania',
    ];

    public function definition(): array
    {
        $nombre = fake()->unique()->randomElement(array_keys(self::FABRICANTES));

        return [
            'nombre'      => $nombre,
            'pais_origen' => self::FABRICANTES[$nombre],
        ];
    }
}
