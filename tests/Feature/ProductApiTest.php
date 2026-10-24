<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'sku' => 'TEST-001',
            'name' => 'Mouse',
            'price_cents' => 1599,
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function accessToken(bool $admin): string
    {
        $user = User::create([
            'name' => $admin ? 'Admin' : 'Cliente',
            'email' => $admin ? 'admin@example.test' : 'cliente@example.test',
            'password' => 'ClaveDemoSegura123!',
        ]);
        if ($admin) {
            $user->forceFill(['is_admin' => true])->save();
        }
        return auth('api')->login($user);
    }

    public function test_catalog_is_public_and_excludes_inactive_products(): void
    {
        $this->product();
        $this->product(['sku' => 'TEST-002', 'is_active' => false]);
        $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.sku', 'TEST-001');
        $this->getJson('/api/v1/products/2')->assertNotFound();
    }

    public function test_guest_cannot_create_product(): void
    {
        $this->postJson('/api/v1/products', [
            'sku' => 'TEST-003', 'name' => 'Teclado', 'price_cents' => 2000, 'stock' => 2,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_create_product(): void
    {
        $token = $this->accessToken(false);
        $this->withToken($token)->postJson('/api/v1/products', [
            'sku' => 'TEST-003', 'name' => 'Teclado', 'price_cents' => 2000, 'stock' => 2,
        ])->assertForbidden();
    }

    public function test_admin_can_create_update_and_deactivate_product(): void
    {
        $token = $this->accessToken(true);
        $id = $this->withToken($token)->postJson('/api/v1/products', [
            'sku' => 'TEST-003', 'name' => 'Teclado', 'price_cents' => 2000, 'stock' => 2,
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->patchJson('/api/v1/products/'.$id, [
            'stock' => 4,
        ])->assertOk()->assertJsonPath('data.stock', 4);
        $this->withToken($token)->deleteJson('/api/v1/products/'.$id)->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id, 'is_active' => false]);
        $this->getJson('/api/v1/products/'.$id)->assertNotFound();
    }

    public function test_invalid_and_duplicate_product_are_rejected(): void
    {
        $token = $this->accessToken(true);
        $this->product();
        $this->withToken($token)->postJson('/api/v1/products', [
            'sku' => 'TEST-001', 'name' => 'Invalido', 'price_cents' => -2, 'stock' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'price_cents', 'stock']);
    }
}
