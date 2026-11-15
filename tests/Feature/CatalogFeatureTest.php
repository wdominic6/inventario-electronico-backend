<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listado_y_busqueda_de_categorias(): void
    {
        $ventas = Usuario::query()->where('email', 'ventas@inventario.test')->firstOrFail();
        Sanctum::actingAs($ventas);

        $response = $this->getJson('/api/categories?q=Semiconductores');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code_prefix', 'description'],
                ],
            ]);

        $firstId = $response->json('data.0.id');
        $this->getJson("/api/categories/{$firstId}")
            ->assertOk()
            ->assertJsonPath('data.id', $firstId);
    }

    public function test_crud_completo_de_categorias_y_bloqueo_409_con_productos(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $createResponse = $this->postJson('/api/categories', [
            'name' => 'Optoelectrónica Industrial',
            'code_prefix' => 'OPTO',
            'description' => 'Optoacopladores, fotodiodos y módulos láser',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Optoelectrónica Industrial')
            ->assertJsonPath('data.code_prefix', 'OPTO');

        $categoryId = (int) $createResponse->json('data.id');

        $this->putJson("/api/categories/{$categoryId}", [
            'name' => 'Optoelectrónica de Precisión',
            'code_prefix' => 'OPTP',
            'description' => 'Sensores ópticos industriales actualizados',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Optoelectrónica de Precisión')
            ->assertJsonPath('data.code_prefix', 'OPTP');

        // Categoría con productos asociados no puede eliminarse (409 Conflict)
        $categoryWithProducts = Categoria::query()->whereHas('productos')->firstOrFail();
        $this->deleteJson("/api/categories/{$categoryWithProducts->id_categoria}")
            ->assertStatus(409);

        // Categoría recién creada sin productos se elimina con 204 No Content
        $this->deleteJson("/api/categories/{$categoryId}")
            ->assertNoContent();
    }

    public function test_crud_de_productos_sincroniza_stock_minimos_y_valida_baja(): void
    {
        $admin = Usuario::query()->where('email', 'admin@inventario.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $category = Categoria::query()->firstOrFail();

        $storeResponse = $this->postJson('/api/products', [
            'sku' => 'MCU-RP2040-TEST',
            'barcode' => '7759990001234',
            'name' => 'Microcontrolador RP2040 Dual ARM Cortex-M0+',
            'category_id' => $category->id_categoria,
            'brand' => 'Raspberry Pi',
            'model' => 'RP2040-QFN56',
            'unit' => 'UND',
            'unit_cost' => 4.20,
            'unit_price' => 8.50,
            'min_stock' => 25,
            'specs' => [
                'package_type' => 'QFN-56',
                'voltage' => '3.3V',
                'tolerance' => '±1%',
            ],
            'is_active' => true,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('data.sku', 'MCU-RP2040-TEST')
            ->assertJsonPath('data.min_stock', 25)
            ->assertJsonPath('data.specs.package_type', 'QFN-56');

        $productId = (int) $storeResponse->json('data.id');

        // Verifica sincronización automática en tabla stock_minimos para sucursales activas
        $minStockRows = DB::table('stock_minimos')
            ->where('id_producto', $productId)
            ->where('stock_minimo', 25)
            ->count();
        $this->assertGreaterThan(0, $minStockRows);

        // Filtrado y consulta individual
        $this->getJson("/api/products?q=MCU-RP2040-TEST&category_id={$category->id_categoria}&is_active=1")
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'MCU-RP2040-TEST');

        $this->getJson("/api/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.id', $productId);

        // Actualización de producto y stock mínimo
        $this->putJson("/api/products/{$productId}", [
            'name' => 'Microcontrolador RP2040 Rev B3',
            'min_stock' => 40,
            'unit_price' => 9.00,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Microcontrolador RP2040 Rev B3')
            ->assertJsonPath('data.min_stock', 40);

        // Producto con stock > 0 bloquea eliminación con 409 Conflict
        $productWithStock = Stock::query()->where('cantidad', '>', 0)->firstOrFail();
        $this->deleteJson("/api/products/{$productWithStock->id_producto}")
            ->assertStatus(409);

        // Producto sin stock se elimina con 204 No Content
        $this->deleteJson("/api/products/{$productId}")
            ->assertNoContent();
    }

    public function test_rol_ventas_no_puede_modificar_catalogo(): void
    {
        $ventas = Usuario::query()->where('email', 'ventas@inventario.test')->firstOrFail();
        Sanctum::actingAs($ventas);

        $product = Producto::query()->firstOrFail();

        $this->postJson('/api/categories', [
            'name' => 'No Autorizado',
            'code_prefix' => 'NAUT',
        ])->assertForbidden();

        $this->deleteJson("/api/products/{$product->id_producto}")
            ->assertForbidden();
    }
}
