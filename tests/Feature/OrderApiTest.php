<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, int $price = 1599, int $stock = 10, bool $active = true): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => "Producto {$sku}",
            'price_cents' => $price,
            'stock' => $stock,
            'is_active' => $active,
        ]);
    }

    private function token(string $email = 'cliente@example.test'): string
    {
        $user = User::create([
            'name' => 'Cliente de pruebas',
            'email' => $email,
            'password' => 'ClaveDemoSegura123!',
        ]);
        $token = auth('api')->login($user);
        auth('api')->forgetUser();
        return $token;
    }

    public function test_guest_cannot_list_or_create_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->postJson('/api/v1/orders', ['items' => []])->assertUnauthorized();
    }

    public function test_customer_creates_order_using_database_prices_and_reserves_stock(): void
    {
        $token = $this->token();
        $mouse = $this->product('TEST-MOUSE', 1599, 5);
        $hub = $this->product('TEST-HUB', 2499, 3);

        $response = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $mouse->id, 'quantity' => 2],
                ['product_id' => $hub->id, 'quantity' => 1],
            ],
        ])->assertCreated()
          ->assertJsonPath('data.status', 'pending')
          ->assertJsonPath('data.total_cents', 5697)
          ->assertJsonPath('data.currency', 'USD')
          ->assertJsonPath('data.payment.status', 'pending')
          ->assertJsonPath('data.payment.amount_cents', 5697);

        $orderId = $response->json('data.id');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $mouse->id,
            'name_snapshot' => 'Producto TEST-MOUSE',
            'unit_price_cents' => 1599,
            'quantity' => 2,
            'subtotal_cents' => 3198,
        ]);
        $this->assertDatabaseHas('products', ['id' => $mouse->id, 'stock' => 3]);
        $this->assertDatabaseHas('products', ['id' => $hub->id, 'stock' => 2]);
    }

    public function test_insufficient_stock_rolls_back_every_change(): void
    {
        $token = $this->token();
        $available = $this->product('TEST-OK', 1000, 4);
        $unavailable = $this->product('TEST-EMPTY', 1000, 0);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $available->id, 'quantity' => 2],
                ['product_id' => $unavailable->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('products', ['id' => $available->id, 'stock' => 4]);
    }

    public function test_inactive_product_cannot_be_purchased(): void
    {
        $token = $this->token();
        $inactive = $this->product('TEST-OFF', 1000, 10, false);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $inactive->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_products_and_client_defined_prices_are_rejected(): void
    {
        $token = $this->token();
        $item = $this->product('TEST-TAMPER');

        $this->withToken($token)->postJson('/api/v1/orders', [
            'total_cents' => 1,
            'items' => [['product_id' => $item->id, 'quantity' => 1, 'unit_price_cents' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['total_cents', 'items.0.unit_price_cents']);

        $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $item->id, 'quantity' => 1],
                ['product_id' => $item->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customers_only_see_their_own_orders(): void
    {
        $alice = $this->token('alice@example.test');
        $item = $this->product('TEST-PRIVATE');
        $orderId = $this->withToken($alice)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        auth('api')->forgetUser();
        $bob = $this->token('bob@example.test');
        $this->withToken($bob)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.total', 0);
        $this->withToken($bob)->getJson('/api/v1/orders/'.$orderId)->assertNotFound();
        $this->withToken($bob)->postJson('/api/v1/orders/'.$orderId.'/cancel')->assertNotFound();
    }

    public function test_cancel_restores_stock_once_and_cancels_payment(): void
    {
        $token = $this->token();
        $item = $this->product('TEST-CANCEL', 2000, 5);
        $orderId = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $item->id, 'quantity' => 3]],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('products', ['id' => $item->id, 'stock' => 2]);
        $this->withToken($token)->postJson('/api/v1/orders/'.$orderId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment.status', 'cancelled');
        $this->assertDatabaseHas('products', ['id' => $item->id, 'stock' => 5]);

        $this->withToken($token)->postJson('/api/v1/orders/'.$orderId.'/cancel')->assertStatus(409);
        $this->assertDatabaseHas('products', ['id' => $item->id, 'stock' => 5]);
    }

    public function test_checkout_started_prevents_local_cancellation(): void
    {
        $token = $this->token();
        $item = $this->product('TEST-STRIPE', 1000, 5);
        $orderId = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $order = Order::findOrFail($orderId);
        $order->payment->update(['stripe_checkout_session_id' => 'cs_test_fake_123']);

        $this->withToken($token)->postJson('/api/v1/orders/'.$orderId.'/cancel')->assertStatus(409);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $item->id, 'stock' => 4]);
    }
}
