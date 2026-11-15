<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (Rol::query()->count() === 0) {
            $this->seed(DatabaseSeeder::class);
        }
    }

    public function test_ventas_puede_consultar_stock_por_criterio_y_sucursal(): void
    {
        $ventas = $this->user('ventas@inventario.test');
        Sanctum::actingAs($ventas);

        $response = $this->getJson('/api/stock/search?q=RES&branch_id=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'sku',
                        'name',
                        'category' => ['id', 'name'],
                        'total_stock',
                        'stocks_by_branch' => [
                            '*' => [
                                'branch_id',
                                'branch_name',
                                'total_quantity',
                                'locations' => [
                                    '*' => ['location_id', 'shelf', 'level', 'bin', 'quantity'],
                                ],
                            ],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertTrue(
            collect($response->json('data'))
                ->contains(fn (array $product): bool => collect($product['stocks_by_branch'])
                    ->contains('branch_id', 1))
        );
    }

    public function test_busqueda_de_stock_exige_dos_caracteres(): void
    {
        Sanctum::actingAs($this->user('ventas@inventario.test'));

        $this->getJson('/api/stock/search?q=R')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_alertas_de_stock_minimo_filtran_por_sucursal_y_severidad(): void
    {
        Sanctum::actingAs($this->user('admin@inventario.test'));

        $response = $this->getJson('/api/alerts/low-stock?branch_id=2&severity=warning');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'product_id',
                        'sku',
                        'name',
                        'branch_id',
                        'branch_name',
                        'current_stock',
                        'min_stock',
                        'severity',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($response->json('data') as $alert) {
            $this->assertSame(2, $alert['branch_id']);
            $this->assertSame('warning', $alert['severity']);
            $this->assertGreaterThan(0, $alert['current_stock']);
            $this->assertLessThanOrEqual($alert['min_stock'], $alert['current_stock']);
        }

        $this->getJson('/api/alerts/low-stock?severity=critical')
            ->assertOk()
            ->assertJsonPath('data.0.severity', 'critical');
    }

    public function test_admin_puede_consultar_resumen_y_rotacion(): void
    {
        Sanctum::actingAs($this->user('admin@inventario.test'));

        $this->getJson('/api/reports/dashboard-summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'active_skus',
                    'inventory_value',
                    'low_stock_alerts',
                    'movements_today',
                    'movements_this_week',
                    'operational_branches',
                ],
            ])
            ->assertJsonPath('data.operational_branches', 3);

        $rotation = $this->getJson('/api/reports/rotation?date_from=2026-01-01&date_to=2026-12-31');

        $rotation->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'product_id',
                        'sku',
                        'name',
                        'category',
                        'movement_count',
                        'quantity_moved',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertNotEmpty($rotation->json('data'));
    }

    public function test_almacen_puede_consultar_kardex_de_un_producto(): void
    {
        Sanctum::actingAs($this->user('almacen@inventario.test'));

        $product = Producto::query()->firstOrFail();

        $this->getJson("/api/reports/kardex/{$product->id_producto}?branch_id=1")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'factor',
                        'quantity',
                        'previous_stock',
                        'resulting_stock',
                        'branch' => ['id', 'name'],
                        'location' => ['id', 'shelf', 'level', 'bin'],
                        'user' => ['id', 'name'],
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.branch.id', 1);
    }

    public function test_ventas_no_puede_consultar_reportes_administrativos(): void
    {
        Sanctum::actingAs($this->user('ventas@inventario.test'));

        $this->getJson('/api/reports/dashboard-summary')
            ->assertForbidden();

        $this->getJson('/api/reports/rotation')
            ->assertForbidden();
    }

    private function user(string $email): Usuario
    {
        return Usuario::query()
            ->where('email', $email)
            ->firstOrFail();
    }
}
