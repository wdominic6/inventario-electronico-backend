<?php

namespace Tests\Feature;

use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAuditFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listado_y_filtros_de_usuarios_por_admin(): void
    {
        $admin = Usuario::query()->where('email', 'admin@inventario.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/users?role=almacen&is_active=1&q=almacen')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'almacen')
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_creacion_y_actualizacion_de_usuarios_registra_bitacora_de_auditoria(): void
    {
        $admin = Usuario::query()->where('email', 'admin@inventario.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $branch = Sucursal::query()->firstOrFail();

        $createResponse = $this->postJson('/api/users', [
            'name' => 'William Auditor Test',
            'email' => 'william.auditor@inventario.test',
            'password' => 'Auditor#2026',
            'role' => 'almacen',
            'branch_id' => $branch->id_sucursal,
            'is_active' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.email', 'william.auditor@inventario.test')
            ->assertJsonPath('data.role', 'almacen')
            ->assertJsonPath('data.is_active', true);

        $userId = (int) $createResponse->json('data.id');

        $this->putJson("/api/users/{$userId}", [
            'name' => 'William Supervisor Almacén',
            'role' => 'ventas',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'William Supervisor Almacén')
            ->assertJsonPath('data.role', 'ventas')
            ->assertJsonPath('data.is_active', false);

        // Consulta de bitácora de auditoría filtrada por acción y usuario
        $today = now()->toDateString();
        $auditResponse = $this->getJson("/api/audit-logs?user_id={$admin->id_usuario}&action=user.updated&date_from={$today}&date_to={$today}");

        $auditResponse->assertOk()
            ->assertJsonPath('data.0.action', 'user.updated')
            ->assertJsonPath('data.0.user_id', $admin->id_usuario);
    }

    public function test_roles_no_administradores_no_acceden_a_usuarios_ni_auditoria(): void
    {
        $almacen = Usuario::query()->where('email', 'almacen@inventario.test')->firstOrFail();
        Sanctum::actingAs($almacen);

        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
    }
}
