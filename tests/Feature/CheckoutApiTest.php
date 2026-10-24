<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\StripeCheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email = 'customer@example.test'): string
    {
        $user = User::create(['name' => 'Test customer', 'email' => $email, 'password' => 'Password123!']);
        $token = auth('api')->login($user);
        auth('api')->forgetUser();
        return $token;
    }

    private function order(string $token): Order
    {
        $product = Product::create([
            'sku' => 'T-'.uniqid(),
            'name' => 'Example product',
            'price_cents' => 1499,
            'stock' => 5,
            'is_active' => true,
        ]);

        $id = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        return Order::findOrFail($id);
    }

    private function stripeEnabled(): void
    {
        config()->set('services.stripe.secret', 'sk_test_not_real_only_for_mocked_tests');
    }

    public function test_guest_cannot_start_checkout(): void
    {
        $this->postJson('/api/v1/orders/123/checkout')->assertUnauthorized();
    }

    public function test_customer_cannot_checkout_another_users_order(): void
    {
        $owner = $this->token('alice@example.test');
        $order = $this->order($owner);
        auth('api')->forgetUser();
        $other = $this->token('bob@example.test');
        $this->stripeEnabled();

        $this->withToken($other)->postJson("/api/v1/orders/{$order->id}/checkout")->assertNotFound();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
    }

    public function test_checkout_without_stripe_key_returns_503(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        config()->set('services.stripe.secret', null);
        $this->withToken($token)->postJson("/api/v1/orders/{$order->id}/checkout")->assertStatus(503);
    }

    public function test_checkout_creates_session_using_order_lines_and_preserves_pending_order(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        $this->stripeEnabled();

        $this->mock(StripeCheckoutGateway::class, function (MockInterface $mock) use ($order): void {
            $mock->shouldReceive('create')->once()
                ->withArgs(function (Order $o) use ($order): bool {
                    return $o->id === $order->id && $o->items()->count() === 1
                        && $o->total_cents === 2998;
                })
                ->andReturn([
                    'id' => 'cs_test_session_ABC',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_session_ABC',
                    'status' => 'open',
                ]);
        });

        $this->withToken($token)->postJson("/api/v1/orders/{$order->id}/checkout", [
            'amount_cents' => 1,
            'status' => 'paid',
        ])->assertOk()
            ->assertJsonPath('data.checkout_session_id', 'cs_test_session_ABC')
            ->assertJsonPath('data.payment_status', 'checkout_pending');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'checkout_pending',
            'stripe_checkout_session_id' => 'cs_test_session_ABC',
            'amount_cents' => 2998,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_repeated_checkout_retrieves_existing_session_instead_of_creating(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        $this->stripeEnabled();
        $order->payment->update([
            'status' => 'checkout_pending',
            'stripe_checkout_session_id' => 'cs_test_existing',
        ]);

        $this->mock(StripeCheckoutGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
            $mock->shouldReceive('retrieve')->once()->with('cs_test_existing')->andReturn([
                'id' => 'cs_test_existing',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_existing',
                'status' => 'open',
            ]);
        });

        $this->withToken($token)->postJson("/api/v1/orders/{$order->id}/checkout")
            ->assertOk()->assertJsonPath('data.checkout_session_id', 'cs_test_existing');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_expired_checkout_does_not_create_another_session(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        $this->stripeEnabled();
        $order->payment->update([
            'status' => 'checkout_pending',
            'stripe_checkout_session_id' => 'cs_test_expired',
        ]);

        $this->mock(StripeCheckoutGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
            $mock->shouldReceive('retrieve')->once()->andReturn([
                'id' => 'cs_test_expired', 'url' => null, 'status' => 'expired',
            ]);
        });

        $this->withToken($token)->postJson("/api/v1/orders/{$order->id}/checkout")->assertStatus(409);
    }

    public function test_cancelled_order_cannot_start_checkout(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        $this->stripeEnabled();
        $order->update(['status' => 'cancelled']);
        $this->withToken($token)->postJson("/api/v1/orders/{$order->id}/checkout")->assertStatus(409);
    }

    public function test_return_urls_do_not_change_payment_or_stock(): void
    {
        $token = $this->token();
        $order = $this->order($token);
        $productId = $order->items->first()->product_id;
        $stock = Product::findOrFail($productId)->stock;

        $this->getJson('/api/v1/checkout/success?session_id=cs_test_not_authoritative')->assertOk();
        $this->getJson('/api/v1/checkout/cancel')->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => $stock]);
    }
}
