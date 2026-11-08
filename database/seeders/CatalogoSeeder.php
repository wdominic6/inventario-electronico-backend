<?php

namespace Database\Seeders;

use App\Models\Atributo;
use App\Models\Categoria;
use App\Models\Fabricante;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;
use RuntimeException;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [];
        foreach ([['Unidad', 'und'], ['Paquete', 'paq']] as [$nombre, $abreviatura]) {
            $unidades[$nombre] = UnidadMedida::create(['nombre' => $nombre, 'abreviatura' => $abreviatura])->id_unidad;
        }

        $fabricantes = [];
        foreach ($this->fabricantes() as $nombre => $pais) {
            $fabricantes[$nombre] = Fabricante::create(['nombre' => $nombre, 'pais_origen' => $pais])->id_fabricante;
        }

        $categorias = [];
        foreach ($this->categorias() as $nombre => $descripcion) {
            $categorias[$nombre] = Categoria::create(['nombre_categoria' => $nombre, 'descripcion' => $descripcion]);
        }

        $atributos = [];
        foreach ($this->atributos() as [$nombre, $tipo, $unidad]) {
            $atributos[$nombre] = Atributo::create([
                'nombre_atributo' => $nombre,
                'tipo_dato'       => $tipo,
                'unidad_medida'   => $unidad,
            ])->id_atributo;
        }

        // Atributos que corresponden a cada categoría (formulario dinámico de RF02).
        $obligatorios = [];
        foreach ($this->atributosPorCategoria() as $categoria => $lista) {
            foreach ($lista as $atributo => $esObligatorio) {
                $categorias[$categoria]->atributos()->attach($atributos[$atributo], ['es_obligatorio' => $esObligatorio]);
                if ($esObligatorio) {
                    $obligatorios[$categoria][] = $atributo;
                }
            }
        }

        foreach ($this->productos() as $p) {
            $faltantes = array_diff($obligatorios[$p['categoria']] ?? [], array_keys($p['atributos']));
            if ($faltantes) {
                throw new RuntimeException("{$p['codigo']}: faltan atributos obligatorios: " . implode(', ', $faltantes));
            }

            $producto = Producto::factory()->create([
                'id_categoria'           => $categorias[$p['categoria']]->id_categoria,
                'id_fabricante'          => $fabricantes[$p['fabricante']],
                'id_unidad'              => $unidades[$p['unidad']],
                'codigo'                 => $p['codigo'],
                'nombre'                 => $p['nombre'],
                'modelo'                 => $p['modelo'],
                'encapsulado'            => $p['encapsulado'],
                'voltaje_operacion'      => $p['voltaje'],
                'tolerancia'             => $p['tolerancia'],
                'especificaciones_extra' => $p['extra'] ?: null,
                'estado'                 => 'activo',
            ]);

            // Cada valor va en UNA sola columna (lo exige el CHECK chk_atributo_valor_unico).
            foreach ($p['atributos'] as $nombre => $valor) {
                $columna = is_bool($valor) ? 'valor_booleano' : (is_numeric($valor) ? 'valor_numero' : 'valor_texto');
                $producto->atributos()->attach($atributos[$nombre], [$columna => $valor]);
            }
        }
    }

    private function fabricantes(): array
    {
        return [
            'Yageo' => 'Taiwán', 'Vishay' => 'EE. UU.', 'Murata' => 'Japón', 'Nichicon' => 'Japón',
            'Kingbright' => 'Taiwán', 'onsemi' => 'EE. UU.', 'Infineon' => 'Alemania',
            'Texas Instruments' => 'EE. UU.', 'STMicroelectronics' => 'Suiza', 'Microchip' => 'EE. UU.',
            'Espressif' => 'China', 'Arduino' => 'Italia', 'DOIT' => 'China', 'Raspberry Pi' => 'Reino Unido',
            'Aosong' => 'China', 'Analog Devices' => 'EE. UU.', 'TDK InvenSense' => 'Japón',
            'Bosch Sensortec' => 'Alemania', 'Hanwei' => 'China', 'Allegro MicroSystems' => 'EE. UU.',
            'Songle' => 'China', 'Genérico' => null,
        ];
    }

    private function categorias(): array
    {
        return [
            'Resistencias'         => 'Resistencias fijas de película de carbono, metal y SMD',
            'Condensadores'        => 'Condensadores cerámicos y electrolíticos',
            'Semiconductores'      => 'Diodos, LED y transistores',
            'Circuitos integrados' => 'Amplificadores, temporizadores y reguladores',
            'Microcontroladores'   => 'Microcontroladores y módulos con SoC',
            'Placas de desarrollo' => 'Arduino, ESP32, Raspberry Pi y similares',
            'Sensores'             => 'Sensores ambientales, de movimiento, distancia y corriente',
            'Módulos y displays'   => 'Pantallas y módulos de potencia o actuación',
            'Prototipado'          => 'Protoboards y cableado',
        ];
    }

    /** [nombre, tipo_dato, unidad] */
    private function atributos(): array
    {
        return [
            ['Resistencia', 'numero', 'Ω'],
            ['Potencia nominal', 'numero', 'W'],
            ['Capacitancia', 'numero', 'µF'],
            ['Tensión nominal', 'numero', 'V'],
            ['Color', 'texto', null],
            ['Núcleo', 'texto', null],
            ['Frecuencia de reloj', 'numero', 'MHz'],
            ['Memoria Flash', 'numero', 'KB'],
            ['Memoria RAM', 'numero', 'KB'],
            ['Pines GPIO', 'numero', null],
            ['WiFi integrado', 'booleano', null],
            ['Conectividad inalámbrica', 'texto', null],
            ['Variable medida', 'texto', null],
            ['Interfaz de comunicación', 'texto', null],
            ['Rango de medición', 'texto', null],
            ['Precisión', 'texto', null],
        ];
    }

    /** categoría => [atributo => es_obligatorio] */
    private function atributosPorCategoria(): array
    {
        return [
            'Resistencias'         => ['Resistencia' => true, 'Potencia nominal' => true],
            'Condensadores'        => ['Capacitancia' => true, 'Tensión nominal' => true],
            'Semiconductores'      => ['Tensión nominal' => false, 'Color' => false],
            'Circuitos integrados' => [],
            'Microcontroladores'   => [
                'Núcleo' => true, 'Frecuencia de reloj' => true, 'Memoria Flash' => true, 'Memoria RAM' => true,
                'Pines GPIO' => false, 'WiFi integrado' => false, 'Conectividad inalámbrica' => false,
            ],
            'Placas de desarrollo' => [
                'Núcleo' => true, 'Frecuencia de reloj' => true, 'WiFi integrado' => true,
                'Memoria Flash' => false, 'Memoria RAM' => false, 'Pines GPIO' => false,
                'Conectividad inalámbrica' => false,
            ],
            'Sensores'             => [
                'Variable medida' => true, 'Interfaz de comunicación' => true,
                'Rango de medición' => false, 'Precisión' => false,
            ],
            'Módulos y displays'   => ['Interfaz de comunicación' => true],
            'Prototipado'          => [],
        ];
    }

    private function p(
        string $codigo, string $nombre, ?string $modelo, ?string $encapsulado, ?string $voltaje,
        ?string $tolerancia, string $fabricante, string $categoria,
        array $atributos = [], array $extra = [], string $unidad = 'Unidad'
    ): array {
        return compact('codigo', 'nombre', 'modelo', 'encapsulado', 'voltaje', 'tolerancia', 'fabricante', 'categoria', 'atributos', 'extra', 'unidad');
    }

    private function productos(): array
    {
        return [
            // ---------- Resistencias ----------
            $this->p('RES-CF-220R', 'Resistencia de película de carbono 220 Ω 1/4 W', 'CFR-25JB-52-220R', 'Axial THT', null, '±5%', 'Yageo', 'Resistencias',
                ['Resistencia' => 220, 'Potencia nominal' => 0.25], ['codigo_colores' => 'Rojo-Rojo-Marrón-Dorado']),
            $this->p('RES-CF-1K', 'Resistencia de película de carbono 1 kΩ 1/4 W', 'CFR-25JB-52-1K', 'Axial THT', null, '±5%', 'Yageo', 'Resistencias',
                ['Resistencia' => 1000, 'Potencia nominal' => 0.25], ['codigo_colores' => 'Marrón-Negro-Rojo-Dorado']),
            $this->p('RES-CF-4K7', 'Resistencia de película de carbono 4.7 kΩ 1/4 W', 'CFR-25JB-52-4K7', 'Axial THT', null, '±5%', 'Yageo', 'Resistencias',
                ['Resistencia' => 4700, 'Potencia nominal' => 0.25], ['codigo_colores' => 'Amarillo-Violeta-Rojo-Dorado']),
            $this->p('RES-CF-10K', 'Resistencia de película de carbono 10 kΩ 1/4 W', 'CFR-25JB-52-10K', 'Axial THT', null, '±5%', 'Yageo', 'Resistencias',
                ['Resistencia' => 10000, 'Potencia nominal' => 0.25], ['codigo_colores' => 'Marrón-Negro-Naranja-Dorado']),
            $this->p('RES-SMD-10K-0805', 'Resistencia SMD 10 kΩ 1% 0805', 'RC0805FR-0710KL', 'SMD 0805', null, '±1%', 'Yageo', 'Resistencias',
                ['Resistencia' => 10000, 'Potencia nominal' => 0.125], ['marcado' => '1002']),

            // ---------- Condensadores ----------
            $this->p('CAP-CER-100N-50V', 'Capacitor cerámico 100 nF 50 V X7R', null, 'Disco THT', '50V', '±10%', 'Murata', 'Condensadores',
                ['Capacitancia' => 0.1, 'Tensión nominal' => 50]),
            $this->p('CAP-ELE-100U-25V', 'Capacitor electrolítico 100 µF 25 V', null, 'Radial THT', '25V', '±20%', 'Nichicon', 'Condensadores',
                ['Capacitancia' => 100, 'Tensión nominal' => 25]),
            $this->p('CAP-ELE-10U-16V', 'Capacitor electrolítico 10 µF 16 V', null, 'Radial THT', '16V', '±20%', 'Nichicon', 'Condensadores',
                ['Capacitancia' => 10, 'Tensión nominal' => 16]),

            // ---------- Semiconductores ----------
            $this->p('SEM-LED-5MM-ROJO', 'LED 5 mm rojo', 'L-53ID', '5 mm THT', '2V (directa)', null, 'Kingbright', 'Semiconductores',
                ['Color' => 'Rojo']),
            $this->p('SEM-1N4007', 'Diodo rectificador 1N4007', '1N4007', 'DO-41', '1000V (inversa)', null, 'Vishay', 'Semiconductores',
                ['Tensión nominal' => 1000], ['corriente_directa_max' => '1 A']),
            $this->p('SEM-PN2222A', 'Transistor NPN PN2222A', 'PN2222A', 'TO-92', '40V (Vceo)', null, 'onsemi', 'Semiconductores',
                ['Tensión nominal' => 40], ['pines' => ['1' => 'Emisor', '2' => 'Base', '3' => 'Colector'], 'corriente_colector_max' => '600 mA']),
            $this->p('SEM-IRLZ44N', 'MOSFET de potencia canal N IRLZ44N (nivel lógico)', 'IRLZ44N', 'TO-220', '55V (Vds)', null, 'Infineon', 'Semiconductores',
                ['Tensión nominal' => 55], ['pines' => ['1' => 'Gate', '2' => 'Drain', '3' => 'Source'], 'corriente_drenaje_max' => '47 A']),

            // ---------- Circuitos integrados ----------
            $this->p('CIR-LM358', 'Amplificador operacional doble LM358', 'LM358N', 'DIP-8', '3-32V', null, 'Texas Instruments', 'Circuitos integrados',
                [], ['pines' => ['1' => 'OUT A', '2' => 'IN A−', '3' => 'IN A+', '4' => 'GND', '5' => 'IN B+', '6' => 'IN B−', '7' => 'OUT B', '8' => 'V+']]),
            $this->p('CIR-NE555', 'Temporizador NE555', 'NE555P', 'DIP-8', '4.5-16V', null, 'Texas Instruments', 'Circuitos integrados',
                [], ['pines' => ['1' => 'GND', '2' => 'TRIG', '3' => 'OUT', '4' => 'RESET', '5' => 'CTRL', '6' => 'THR', '7' => 'DIS', '8' => 'VCC']]),
            $this->p('CIR-L7805', 'Regulador de voltaje 5 V L7805CV', 'L7805CV', 'TO-220', '7-35V (entrada)', null, 'STMicroelectronics', 'Circuitos integrados',
                [], ['pines' => ['1' => 'Entrada', '2' => 'GND', '3' => 'Salida 5 V'], 'corriente_salida_max' => '1.5 A']),

            // ---------- Microcontroladores ----------
            $this->p('MCU-ATMEGA328P-PU', 'Microcontrolador ATmega328P-PU', 'ATmega328P-PU', 'DIP-28', '1.8-5.5V', null, 'Microchip', 'Microcontroladores',
                ['Núcleo' => 'AVR 8-bit', 'Frecuencia de reloj' => 20, 'Memoria Flash' => 32, 'Memoria RAM' => 2, 'Pines GPIO' => 23, 'WiFi integrado' => false],
                ['adc' => '10 bits, 6 canales', 'pwm' => '6 canales']),
            $this->p('MCU-ATTINY85-20PU', 'Microcontrolador ATtiny85-20PU', 'ATtiny85-20PU', 'DIP-8', '2.7-5.5V', null, 'Microchip', 'Microcontroladores',
                ['Núcleo' => 'AVR 8-bit', 'Frecuencia de reloj' => 20, 'Memoria Flash' => 8, 'Memoria RAM' => 0.5, 'Pines GPIO' => 6, 'WiFi integrado' => false]),
            $this->p('MCU-PIC16F877A', 'Microcontrolador PIC16F877A-I/P', 'PIC16F877A-I/P', 'DIP-40', '4.0-5.5V', null, 'Microchip', 'Microcontroladores',
                ['Núcleo' => 'PIC 8-bit', 'Frecuencia de reloj' => 20, 'Memoria Flash' => 14, 'Memoria RAM' => 0.3594, 'Pines GPIO' => 33, 'WiFi integrado' => false]),
            $this->p('MCU-STM32F103C8T6', 'Microcontrolador STM32F103C8T6', 'STM32F103C8T6', 'LQFP-48', '2.0-3.6V', null, 'STMicroelectronics', 'Microcontroladores',
                ['Núcleo' => 'ARM Cortex-M3', 'Frecuencia de reloj' => 72, 'Memoria Flash' => 64, 'Memoria RAM' => 20, 'Pines GPIO' => 37, 'WiFi integrado' => false],
                ['interfaces' => ['2×SPI', '2×I2C', '3×USART', 'USB 2.0 FS', 'CAN 2.0B'], 'adc' => '2 × 12 bits']),
            $this->p('MCU-ESP32-WROOM32', 'Módulo ESP32-WROOM-32', 'ESP32-WROOM-32', 'Módulo SMD 18 x 25.5 mm', '3.0-3.6V', null, 'Espressif', 'Microcontroladores',
                ['Núcleo' => 'Xtensa LX6 dual-core', 'Frecuencia de reloj' => 240, 'Memoria Flash' => 4096, 'Memoria RAM' => 520, 'WiFi integrado' => true,
                 'Conectividad inalámbrica' => 'WiFi 802.11 b/g/n + Bluetooth v4.2 BR/EDR y BLE'],
                ['interfaces' => ['SPI', 'I2C', 'I2S', 'UART', 'CAN 2.0', 'ADC 12 bits', 'DAC 8 bits']]),

            // ---------- Placas de desarrollo ----------
            $this->p('PLA-ARDUINO-UNO-R3', 'Arduino Uno R3', 'A000066', 'Placa 68.6 x 53.4 mm', '5V (entrada recomendada 7-12V)', null, 'Arduino', 'Placas de desarrollo',
                ['Núcleo' => 'AVR 8-bit (ATmega328P)', 'Frecuencia de reloj' => 16, 'Memoria Flash' => 32, 'Memoria RAM' => 2, 'Pines GPIO' => 20, 'WiFi integrado' => false],
                ['pwm' => 6, 'entradas_analogicas' => 6]),
            $this->p('PLA-ARDUINO-NANO', 'Arduino Nano', 'A000005', 'Placa 45 x 18 mm', '5V', null, 'Arduino', 'Placas de desarrollo',
                ['Núcleo' => 'AVR 8-bit (ATmega328P)', 'Frecuencia de reloj' => 16, 'Memoria Flash' => 32, 'Memoria RAM' => 2, 'WiFi integrado' => false],
                ['pwm' => 6, 'entradas_analogicas' => 8]),
            $this->p('PLA-ARDUINO-MEGA2560', 'Arduino Mega 2560 Rev3', 'A000067', 'Placa 101.5 x 53.3 mm', '5V (entrada recomendada 7-12V)', null, 'Arduino', 'Placas de desarrollo',
                ['Núcleo' => 'AVR 8-bit (ATmega2560)', 'Frecuencia de reloj' => 16, 'Memoria Flash' => 256, 'Memoria RAM' => 8, 'Pines GPIO' => 70, 'WiFi integrado' => false],
                ['pwm' => 15, 'entradas_analogicas' => 16, 'uart' => 4]),
            $this->p('PLA-ESP32-DEVKIT-V1', 'Placa de desarrollo ESP32 DevKit V1 (30 pines)', 'ESP32 DevKit V1', 'Placa 30 pines', '3.3V (lógica), 5V (USB)', null, 'DOIT', 'Placas de desarrollo',
                ['Núcleo' => 'Xtensa LX6 dual-core', 'Frecuencia de reloj' => 240, 'Memoria Flash' => 4096, 'Memoria RAM' => 520, 'WiFi integrado' => true,
                 'Conectividad inalámbrica' => 'WiFi 802.11 b/g/n + Bluetooth v4.2 BR/EDR y BLE'],
                ['interfaces' => ['SPI', 'I2C', 'I2S', 'UART']]),
            $this->p('PLA-NODEMCU-ESP8266', 'NodeMCU ESP8266 (ESP-12E)', 'NodeMCU ESP-12E', 'Placa 30 pines', '3.3V (lógica), 5V (USB)', null, 'Genérico', 'Placas de desarrollo',
                ['Núcleo' => 'Tensilica L106 32-bit', 'Frecuencia de reloj' => 80, 'Memoria Flash' => 4096, 'WiFi integrado' => true,
                 'Conectividad inalámbrica' => 'WiFi 802.11 b/g/n']),
            $this->p('PLA-RPI-PICO', 'Raspberry Pi Pico', 'SC0915', 'Placa 51 x 21 mm', '1.8-5.5V (VSYS)', null, 'Raspberry Pi', 'Placas de desarrollo',
                ['Núcleo' => 'ARM Cortex-M0+ dual-core (RP2040)', 'Frecuencia de reloj' => 133, 'Memoria Flash' => 2048, 'Memoria RAM' => 264, 'Pines GPIO' => 26, 'WiFi integrado' => false],
                ['cabezales_soldados' => false, 'pio' => '8 máquinas de estado PIO']),
            $this->p('PLA-RPI4-4GB', 'Raspberry Pi 4 Model B 4 GB', 'Raspberry Pi 4 Model B', 'Placa 85 x 56 mm', '5V DC (USB-C, 3 A)', null, 'Raspberry Pi', 'Placas de desarrollo',
                ['Núcleo' => 'ARM Cortex-A72 quad-core (BCM2711)', 'Frecuencia de reloj' => 1500, 'Memoria RAM' => 4194304, 'Pines GPIO' => 28, 'WiFi integrado' => true,
                 'Conectividad inalámbrica' => 'WiFi 802.11ac 2.4/5 GHz + Bluetooth 5.0 BLE'],
                ['almacenamiento' => 'microSD', 'puertos' => ['2×micro-HDMI', '2×USB 3.0', '2×USB 2.0', 'Gigabit Ethernet', 'USB-C (alimentación)']]),

            // ---------- Sensores ----------
            $this->p('SEN-DHT22', 'Sensor de temperatura y humedad DHT22 (AM2302)', 'DHT22', 'Módulo 4 pines', '3.3-6V', null, 'Aosong', 'Sensores',
                ['Variable medida' => 'Temperatura y humedad relativa', 'Interfaz de comunicación' => 'Digital de un hilo (propietario)',
                 'Rango de medición' => 'Temp: -40 a 80 °C; HR: 0 a 100 %', 'Precisión' => 'Temp ±0.5 °C; HR ±2 %'],
                ['pines' => ['1' => 'VCC', '2' => 'DATA', '3' => 'NC', '4' => 'GND']]),
            $this->p('SEN-DS18B20', 'Sensor de temperatura digital DS18B20', 'DS18B20', 'TO-92', '3.0-5.5V', null, 'Analog Devices', 'Sensores',
                ['Variable medida' => 'Temperatura', 'Interfaz de comunicación' => '1-Wire',
                 'Rango de medición' => '-55 a 125 °C', 'Precisión' => '±0.5 °C (de -10 a 85 °C)'],
                ['pines' => ['1' => 'GND', '2' => 'DQ', '3' => 'VDD']]),
            $this->p('SEN-HCSR04', 'Sensor ultrasónico de distancia HC-SR04', 'HC-SR04', 'Módulo 4 pines', '5V', null, 'Genérico', 'Sensores',
                ['Variable medida' => 'Distancia (ultrasónico)', 'Interfaz de comunicación' => 'Digital (Trig/Echo)',
                 'Rango de medición' => '2 a 400 cm', 'Precisión' => '±3 mm'],
                ['pines' => ['1' => 'VCC', '2' => 'Trig', '3' => 'Echo', '4' => 'GND']]),
            $this->p('SEN-MPU6050', 'Módulo acelerómetro y giroscopio MPU-6050 (GY-521)', 'GY-521', 'Módulo 8 pines', '3.3-5V', null, 'TDK InvenSense', 'Sensores',
                ['Variable medida' => 'Aceleración y velocidad angular (6 ejes)', 'Interfaz de comunicación' => 'I2C',
                 'Rango de medición' => 'Acel. ±2/4/8/16 g; giro ±250/500/1000/2000 °/s']),
            $this->p('SEN-BME280', 'Sensor ambiental BME280 (temperatura, humedad y presión)', 'BME280', 'Módulo 4 pines', '3.3V', null, 'Bosch Sensortec', 'Sensores',
                ['Variable medida' => 'Temperatura, humedad y presión', 'Interfaz de comunicación' => 'I2C / SPI',
                 'Rango de medición' => 'Temp: -40 a 85 °C; HR: 0-100 %; Presión: 300-1100 hPa',
                 'Precisión' => 'Temp ±1 °C; HR ±3 %; Presión ±1 hPa']),
            $this->p('SEN-HCSR501', 'Sensor de movimiento PIR HC-SR501', 'HC-SR501', 'Módulo 3 pines', '4.5-20V', null, 'Genérico', 'Sensores',
                ['Variable medida' => 'Movimiento (infrarrojo pasivo)', 'Interfaz de comunicación' => 'Digital',
                 'Rango de medición' => 'Hasta 7 m, ángulo aprox. 120°']),
            $this->p('SEN-MQ2', 'Sensor de gas y humo MQ-2', 'MQ-2', 'Módulo 4 pines', '5V', null, 'Hanwei', 'Sensores',
                ['Variable medida' => 'Gases combustibles y humo', 'Interfaz de comunicación' => 'Analógica y digital',
                 'Rango de medición' => '300 a 10000 ppm']),
            $this->p('SEN-INA219', 'Sensor de corriente y tensión INA219', 'INA219', 'Módulo 6 pines', '3-5.5V', null, 'Texas Instruments', 'Sensores',
                ['Variable medida' => 'Corriente y tensión (DC)', 'Interfaz de comunicación' => 'I2C',
                 'Rango de medición' => 'Bus 0-26 V; ±3.2 A (shunt 0.1 Ω)', 'Precisión' => '±0.5 %'],
                ['pines' => ['VCC', 'GND', 'SCL', 'SDA', 'VIN+', 'VIN−']]),
            $this->p('SEN-ACS712-5A', 'Sensor de corriente ACS712 5 A', 'ACS712ELCTR-05B-T', 'Módulo', '5V', null, 'Allegro MicroSystems', 'Sensores',
                ['Variable medida' => 'Corriente (efecto Hall)', 'Interfaz de comunicación' => 'Analógica', 'Rango de medición' => '±5 A']),
            $this->p('SEN-SUELO-CAP', 'Sensor capacitivo de humedad de suelo v1.2', 'v1.2', 'Módulo 3 pines', '3.3-5.5V', null, 'Genérico', 'Sensores',
                ['Variable medida' => 'Humedad del suelo', 'Interfaz de comunicación' => 'Analógica']),

            // ---------- Módulos y displays ----------
            $this->p('MOD-OLED-SSD1306', 'Pantalla OLED 0.96" 128x64 I2C (SSD1306)', 'SSD1306', 'Módulo 4 pines', '3.3-5V', null, 'Genérico', 'Módulos y displays',
                ['Interfaz de comunicación' => 'I2C']),
            $this->p('MOD-LCD1602-I2C', 'Pantalla LCD 16x2 con módulo I2C', 'LCD1602', 'Módulo 4 pines', '5V', null, 'Genérico', 'Módulos y displays',
                ['Interfaz de comunicación' => 'I2C']),
            $this->p('MOD-RELE-5V-1CH', 'Módulo relé 5 V 1 canal', 'SRD-05VDC-SL-C', 'Módulo', '5V', null, 'Songle', 'Módulos y displays',
                ['Interfaz de comunicación' => 'Digital (nivel lógico)']),

            // ---------- Prototipado ----------
            $this->p('PRO-PROTOBOARD-830', 'Protoboard 830 puntos', 'MB-102', 'Placa 165 x 55 mm', null, null, 'Genérico', 'Prototipado'),
            $this->p('PRO-DUPONT-MM-40', 'Cables Dupont macho-macho 20 cm (40 und)', null, null, null, null, 'Genérico', 'Prototipado', [], [], 'Paquete'),
            $this->p('PRO-DUPONT-MH-40', 'Cables Dupont macho-hembra 20 cm (40 und)', null, null, null, null, 'Genérico', 'Prototipado', [], [], 'Paquete'),
        ];
    }
}
