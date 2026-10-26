<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\StripeCheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class PaymentLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_only_for_unit_tests_not_real';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.secret', 'sk_test_fake_only_for_mocks');
        config()->set('services.stripe.webhook_secret', self::WEBHOOK_SECRET);
    }

    private function createOrderAndCheckout(): array
    {
        $user = User::create([
            'name' => 'Cliente de integración',
            'email' => 'integracion@example.test',
            'password' => 'Password123!',
        ]);
        $guard = auth('api');
        /** @var JWTGuard $guard */
        $token = $guard->login($user);
        $guard->forgetUser();

        $product = Product::create([
            'sku' => 'FLOW-001',
            'name' => 'Producto de integración',
            'price_cents' => 2500,
            'stock' => 4,
            'is_active' => true,
        ]);

        $orderId = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data.id');

        $this->mock(StripeCheckoutGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->andReturn([
                'id' => 'cs_test_endtoend_01',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_endtoend_01',
                'status' => 'open',
            ]);
        });

        $this->withToken($token)
            ->postJson('/api/v1/orders/'.$orderId.'/checkout')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'checkout_pending');

        return [Order::findOrFail($orderId), $product->id];
    }

    private function signedEvent(Order $order, string $id, string $type, array $overrides = [], ?int $timestamp = null): \Illuminate\Testing\TestResponse
    {
        $session = array_replace([
            'id' => 'cs_test_endtoend_01',
            'object' => 'checkout.session',
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id],
            'amount_total' => 5000,
            'currency' => 'usd',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_endtoend_01',
        ], $overrides);

        $payload = json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $session],
        ], JSON_THROW_ON_ERROR);
        $time = $timestamp ?? time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    public function test_order_checkout_and_verified_webhook_complete_purchase_without_double_stock_change(): void
    {
        [$order, $productId] = $this->createOrderAndCheckout();

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 2]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

        $this->getJson('/api/v1/checkout/success?session_id=cs_test_endtoend_01')->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

        $this->signedEvent($order, 'evt_flow_paid_01', 'checkout.session.completed')
            ->assertOk()->assertJsonPath('message', 'processed');
        $this->signedEvent($order, 'evt_flow_paid_01', 'checkout.session.completed')
            ->assertOk()->assertJsonPath('message', 'duplicate');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_cents' => 5000, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount_cents' => 5000,
            'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_endtoend_01',
        ]);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 2]);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_different_expiration_events_cannot_restore_stock_twice(): void
    {
        [$order, $productId] = $this->createOrderAndCheckout();

        $this->signedEvent($order, 'evt_flow_expired_01', 'checkout.session.expired', ['payment_status' => 'unpaid'])
            ->assertOk();
        $this->signedEvent($order, 'evt_flow_expired_02', 'checkout.session.expired', ['payment_status' => 'unpaid'])
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 4]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired']);
        $this->assertDatabaseCount('stripe_webhook_events', 2);
    }

    public function test_currency_mismatch_cannot_confirm_payment(): void
    {
        [$order, $productId] = $this->createOrderAndCheckout();

        $this->signedEvent($order, 'evt_flow_bad_currency', 'checkout.session.completed', ['currency' => 'eur'])
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 2]);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_old_signed_webhook_is_rejected(): void
    {
        [$order, $productId] = $this->createOrderAndCheckout();

        $this->signedEvent($order, 'evt_flow_old', 'checkout.session.completed', [], time() - 3600)
            ->assertBadRequest();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 2]);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }
}
