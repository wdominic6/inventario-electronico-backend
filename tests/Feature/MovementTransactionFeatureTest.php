<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovementTransactionFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entrada_incrementa_stock_y_registra_kardex(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $location = Ubicacion::query()->where('id_sucursal', 1)->firstOrFail();
        $product = Producto::query()->firstOrFail();

        $beforeQty = (int) (Stock::query()
            ->where('id_sucursal', 1)
            ->where('id_ubicacion', $location->id_ubicacion)
            ->where('id_producto', $product->id_producto)
            ->value('cantidad') ?? 0);

        $response = $this->postJson('/api/movements/entry', [
            'branch_id' => 1,
            'supplier_name' => 'Mouser Electronics Latam',
            'reference_code' => 'FAC-MOUSER-9001',
            'notes' => 'Abastecimiento de semiconductores de prueba',
            'items' => [
                [
                    'product_id' => $product->id_producto,
                    'location_id' => $location->id_ubicacion,
                    'quantity' => 15,
                    'unit_cost' => 3.50,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'entry')
            ->assertJsonPath('data.items.0.previous_stock', $beforeQty)
            ->assertJsonPath('data.items.0.new_stock', $beforeQty + 15);

        $afterQty = (int) Stock::query()
            ->where('id_sucursal', 1)
            ->where('id_ubicacion', $location->id_ubicacion)
            ->where('id_producto', $product->id_producto)
            ->value('cantidad');

        $this->assertSame($beforeQty + 15, $afterQty);
    }

    public function test_entrada_rechaza_ubicacion_de_otra_sucursal_y_lineas_duplicadas(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $product = Producto::query()->firstOrFail();
        $locationBranch1 = Ubicacion::query()->where('id_sucursal', 1)->firstOrFail();
        $locationBranch2 = Ubicacion::query()->where('id_sucursal', 2)->firstOrFail();

        // Ubicación de sucursal 2 enviada en entrada de sucursal 1 -> 422
        $this->postJson('/api/movements/entry', [
            'branch_id' => 1,
            'supplier_name' => 'DigiKey Corp',
            'items' => [
                [
                    'product_id' => $product->id_producto,
                    'location_id' => $locationBranch2->id_ubicacion,
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.location_id']);

        // Misma tupla (product_id, location_id) duplicada -> 422
        $this->postJson('/api/movements/entry', [
            'branch_id' => 1,
            'supplier_name' => 'DigiKey Corp',
            'items' => [
                [
                    'product_id' => $product->id_producto,
                    'location_id' => $locationBranch1->id_ubicacion,
                    'quantity' => 5,
                ],
                [
                    'product_id' => $product->id_producto,
                    'location_id' => $locationBranch1->id_ubicacion,
                    'quantity' => 10,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.location_id']);
    }

    public function test_salida_descuenta_stock_y_bloquea_sobregiro_con_409_conflict(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $stockRow = Stock::query()->where('cantidad', '>=', 10)->firstOrFail();
        $initialQty = (int) $stockRow->cantidad;

        // Salida válida de 4 unidades
        $this->postJson('/api/movements/exit', [
            'branch_id' => $stockRow->id_sucursal,
            'reason' => 'sale',
            'reference_code' => 'GUIA-SAL-501',
            'customer_reference' => 'Cliente Corporativo Puno',
            'items' => [
                [
                    'product_id' => $stockRow->id_producto,
                    'location_id' => $stockRow->id_ubicacion,
                    'quantity' => 4,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'exit')
            ->assertJsonPath('data.items.0.previous_stock', $initialQty)
            ->assertJsonPath('data.items.0.new_stock', $initialQty - 4);

        // Intento de retirar más stock del disponible -> 409 Conflict y rollback intacto
        $this->postJson('/api/movements/exit', [
            'branch_id' => $stockRow->id_sucursal,
            'reason' => 'sale',
            'items' => [
                [
                    'product_id' => $stockRow->id_producto,
                    'location_id' => $stockRow->id_ubicacion,
                    'quantity' => $initialQty + 5000,
                ],
            ],
        ])->assertStatus(409);

        $currentQty = (int) Stock::query()->where('id_stock', $stockRow->id_stock)->value('cantidad');
        $this->assertSame($initialQty - 4, $currentQty);
    }

    public function test_traslado_entre_sucursales_es_atomico_y_consulta_kardex(): void
    {
        $admin = Usuario::query()->where('email', 'admin@inventario.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $sourceStock = Stock::query()
            ->where('id_sucursal', 1)
            ->where('cantidad', '>=', 10)
            ->firstOrFail();

        $targetLocation = Ubicacion::query()
            ->where('id_sucursal', 2)
            ->firstOrFail();

        $sourceBefore = (int) $sourceStock->cantidad;
        $targetBefore = (int) (Stock::query()
            ->where('id_sucursal', 2)
            ->where('id_ubicacion', $targetLocation->id_ubicacion)
            ->where('id_producto', $sourceStock->id_producto)
            ->value('cantidad') ?? 0);

        $transferResponse = $this->postJson('/api/movements/transfer', [
            'source_branch_id' => 1,
            'target_branch_id' => 2,
            'reference_code' => 'GUIA-TRA-7001',
            'notes' => 'Traslado logístico Puno -> Juliaca',
            'items' => [
                [
                    'product_id' => $sourceStock->id_producto,
                    'source_location_id' => $sourceStock->id_ubicacion,
                    'target_location_id' => $targetLocation->id_ubicacion,
                    'quantity' => 5,
                ],
            ],
        ]);

        $transferResponse->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.items.0.previous_stock', $sourceBefore)
            ->assertJsonPath('data.items.0.new_stock', $sourceBefore - 5)
            ->assertJsonPath('data.items.0.target_previous_stock', $targetBefore)
            ->assertJsonPath('data.items.0.target_new_stock', $targetBefore + 5);

        // Consulta de movimientos filtrados por tipo transfer y producto
        $listResponse = $this->getJson("/api/movements?type=transfer&product_id={$sourceStock->id_producto}");
        $listResponse->assertOk()
            ->assertJsonPath('data.0.type', 'transfer');

        $kardexId = (int) $listResponse->json('data.0.id');
        $this->getJson("/api/movements/{$kardexId}")
            ->assertOk()
            ->assertJsonPath('data.id', $kardexId);
    }
}
