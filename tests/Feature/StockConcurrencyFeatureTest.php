<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Stock;
use App\Models\Usuario;
use App\Services\StockTransactionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class StockConcurrencyFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (Rol::query()->count() === 0) {
            $this->seed(DatabaseSeeder::class);
        }
    }

    public function test_dos_salidas_que_compiten_por_el_mismo_stock_no_generan_sobregiro(): void
    {
        $stock = Stock::query()
            ->where('cantidad', '>=', 10)
            ->firstOrFail();
        $userId = Usuario::query()
            ->where('email', 'almacen@inventario.test')
            ->value('id_usuario');
        $requestedQuantity = intdiv((int) $stock->cantidad, 2) + 1;
        $payload = [
            'branch_id' => $stock->id_sucursal,
            'reason' => 'sale',
            'items' => [[
                'product_id' => $stock->id_producto,
                'location_id' => $stock->id_ubicacion,
                'quantity' => $requestedQuantity,
            ]],
        ];

        $service = app(StockTransactionService::class);

        $first = $service->registerExit(
            [...$payload, 'reference_code' => 'CONC-FIRST-'.uniqid()],
            (int) $userId
        );

        try {
            $service->registerExit(
                [...$payload, 'reference_code' => 'CONC-SECOND-'.uniqid()],
                (int) $userId
            );

            $this->fail('La segunda salida debía rechazarse por stock insuficiente.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('Stock insuficiente', $exception->getMessage());
        }

        $this->assertSame(
            (int) $stock->cantidad - $requestedQuantity,
            (int) Stock::query()->whereKey($stock->id_stock)->value('cantidad')
        );
        $this->assertSame(
            (int) $stock->cantidad - $requestedQuantity,
            $first['items'][0]['new_stock']
        );
    }
}
