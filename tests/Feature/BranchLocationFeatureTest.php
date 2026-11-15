<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchLocationFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listado_de_sucursales_y_ubicaciones_con_filtros(): void
    {
        $ventas = Usuario::query()->where('email', 'ventas@inventario.test')->firstOrFail();
        Sanctum::actingAs($ventas);

        $branchResponse = $this->getJson('/api/branches?is_active=1');
        $branchResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'address', 'phone', 'is_active'],
                ],
            ]);

        $branchId = (int) $branchResponse->json('data.0.id');

        $this->getJson("/api/locations?branch_id={$branchId}&is_active=1")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'branch_id', 'code', 'shelf', 'level', 'bin'],
                ],
            ]);
    }

    public function test_crud_sucursales_exclusivo_admin_y_bloqueo_409_con_stock(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        // El rol almacén no puede crear sucursales
        $this->postJson('/api/branches', [
            'code' => 'SUC-NOAUT',
            'name' => 'Sucursal No Autorizada',
        ])->assertForbidden();

        // El rol admin sí gestiona sucursales
        $admin = Usuario::query()->where('email', 'admin@inventario.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/branches', [
            'code' => 'SUC-ILO-04',
            'name' => 'Sucursal Ilo Puerto',
            'address' => 'Av. Costanera 450, Ilo',
            'phone' => '053-481200',
            'is_active' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.code', 'SUC-ILO-04')
            ->assertJsonPath('data.name', 'Sucursal Ilo Puerto');

        $branchId = (int) $createResponse->json('data.id');

        $this->putJson("/api/branches/{$branchId}", [
            'name' => 'Sucursal Ilo Logística',
            'phone' => '053-489999',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Sucursal Ilo Logística')
            ->assertJsonPath('data.phone', '053-489999');

        // Sucursal con stock activo > 0 devuelve 409 Conflict
        $branchWithStock = Stock::query()->where('cantidad', '>', 0)->firstOrFail();
        $this->deleteJson("/api/branches/{$branchWithStock->id_sucursal}")
            ->assertStatus(409);

        // Sucursal vacía se elimina con 204 No Content
        $this->deleteJson("/api/branches/{$branchId}")
            ->assertNoContent();
    }

    public function test_crud_ubicaciones_fisicas_y_proteccion_de_stock(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $branch = Sucursal::query()->firstOrFail();

        $createResponse = $this->postJson('/api/locations', [
            'branch_id' => $branch->id_sucursal,
            'code' => 'EST-Z9-N3-C8',
            'shelf' => 'Z9',
            'level' => 'N3',
            'bin' => 'C8',
            'description' => 'Gaveta antiestática para sensores MEMS',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.code', 'EST-Z9-N3-C8')
            ->assertJsonPath('data.shelf', 'Z9');

        $locationId = (int) $createResponse->json('data.id');

        $this->putJson("/api/locations/{$locationId}", [
            'shelf' => 'Z9-MOD',
            'description' => 'Gaveta antiestática actualizada',
        ])->assertOk()
            ->assertJsonPath('data.shelf', 'Z9-MOD');

        // Ubicación con stock > 0 no puede eliminarse (409 Conflict)
        $locationWithStock = Stock::query()->where('cantidad', '>', 0)->firstOrFail();
        $this->deleteJson("/api/locations/{$locationWithStock->id_ubicacion}")
            ->assertStatus(409);

        // Ubicación sin stock se elimina con 204 No Content
        $this->deleteJson("/api/locations/{$locationId}")
            ->assertNoContent();
    }
}
