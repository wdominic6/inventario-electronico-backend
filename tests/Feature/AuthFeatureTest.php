<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (Rol::query()->count() === 0) {
            $this->seed(DatabaseSeeder::class);
        }
    }

    public function test_login_exitoso_retorna_token_sanctum_y_actualiza_ultimo_acceso(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@inventario.test',
            'password' => 'Admin#2026',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'is_active', 'branch_id', 'last_access'],
                ],
            ])
            ->assertJsonPath('data.user.email', 'admin@inventario.test')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.is_active', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($response->json('data.user.last_access'));
    }

    public function test_login_con_credenciales_incorrectas_retorna_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@inventario.test',
            'password' => 'ClaveIncorrecta123',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_login_con_cuenta_inactiva_retorna_403(): void
    {
        $rolVentas = Rol::query()->where('nombre_rol', Rol::VENTAS)->firstOrFail();

        Usuario::query()->create([
            'id_rol' => $rolVentas->id_rol,
            'nombre' => 'Cuenta',
            'apellido' => 'Inactiva',
            'email' => 'inactivo.test@inventario.test',
            'password_hash' => Hash::make('Secreto#2026'),
            'estado' => 'inactivo',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactivo.test@inventario.test',
            'password' => 'Secreto#2026',
        ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_me_y_logout_revocan_token_correctamente(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'ventas@inventario.test',
            'password' => 'Ventas#2026',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ventas@inventario.test')
            ->assertJsonPath('data.role', 'ventas');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_control_de_acceso_por_roles_bloquea_rutas_no_autorizadas(): void
    {
        $loginVentas = $this->postJson('/api/auth/login', [
            'email' => 'ventas@inventario.test',
            'password' => 'Ventas#2026',
        ])->assertOk();

        $tokenVentas = $loginVentas->json('data.token');

        // Ventas no puede acceder a gestión de usuarios (exclusivo admin)
        $this->withHeader('Authorization', 'Bearer '.$tokenVentas)
            ->getJson('/api/users')
            ->assertStatus(403);

        // Ventas tampoco puede crear productos en el catálogo (exclusivo admin y almacen)
        $this->withHeader('Authorization', 'Bearer '.$tokenVentas)
            ->postJson('/api/products', [])
            ->assertStatus(403);
    }
}
