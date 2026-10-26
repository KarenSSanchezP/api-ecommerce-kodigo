<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_unit_tests_only_do_not_use_for_live';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.webhook_secret', self::SECRET);
    }

    private function order(): Order
    {
        $user = User::create([
            'name' => 'Cliente Test',
            'email' => 'webhook@example.test',
            'password' => 'Password123!',
        ]);
        $product = Product::create([
            'sku' => 'WEBHOOK-01', 'name' => 'Mouse',
            'price_cents' => 1499, 'stock' => 5, 'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'status' => 'pending',
            'total_cents' => 2998, 'currency' => 'USD',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'name_snapshot' => 'Mouse',
            'unit_price_cents' => 1499, 'quantity' => 2, 'subtotal_cents' => 2998,
        ]);
        $product->decrement('stock', 2);
        $order->payment()->create([
            'provider' => 'stripe', 'status' => 'checkout_pending',
            'amount_cents' => 2998,
            'stripe_checkout_session_id' => 'cs_test_webhook_01',
        ]);
        return $order->refresh();
    }

    /** @return array<string, mixed> */
    private function event(Order $order, string $eventId, string $type, string $status = 'paid'): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'cs_test_webhook_01',
                    'object' => 'checkout.session',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                    'amount_total' => 2998,
                    'currency' => 'usd',
                    'payment_status' => $status,
                    'payment_intent' => 'pi_test_webhook_01',
                ],
            ],
        ];
    }

    private function send(array $event, bool $validSignature = true): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $secret = $validSignature ? self::SECRET : 'wrong-signing-key';
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $payload);
    }

    public function test_webhook_requires_configured_secret(): void
    {
        config()->set('services.stripe.webhook_secret', null);
        $this->postJson('/api/v1/webhooks/stripe')->assertStatus(503);
    }

    public function test_invalid_signature_does_not_modify_payment(): void
    {
        $order = $this->order();
        $this->send($this->event($order, 'evt_invalid_01', 'checkout.session.completed'), false)
            ->assertBadRequest();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_paid_checkout_confirms_payment_and_order(): void
    {
        $order = $this->order();
        $this->send($this->event($order, 'evt_paid_01', 'checkout.session.completed'))
            ->assertOk()->assertJsonPath('message', 'processed');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test_webhook_01',
        ]);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_duplicate_paid_event_is_idempotent(): void
    {
        $order = $this->order();
        $event = $this->event($order, 'evt_replay_01', 'checkout.session.completed');
        $this->send($event)->assertOk();
        $this->send($event)->assertOk()->assertJsonPath('message', 'duplicate');
        $this->assertDatabaseCount('stripe_webhook_events', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_unpaid_completed_waits_for_async_success(): void
    {
        $order = $this->order();
        $this->send($this->event($order, 'evt_unpaid_01', 'checkout.session.completed', 'unpaid'))
            ->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->send($this->event($order, 'evt_async_paid_01', 'checkout.session.async_payment_succeeded'))
            ->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_expired_checkout_returns_stock_exactly_once(): void
    {
        $order = $this->order();
        $productId = $order->items->first()->product_id;
        $event = $this->event($order, 'evt_expired_01', 'checkout.session.expired', 'unpaid');
        $this->send($event)->assertOk();
        $this->send($event)->assertOk()->assertJsonPath('message', 'duplicate');
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'expired']);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
    }

    public function test_async_failure_restores_stock(): void
    {
        $order = $this->order();
        $productId = $order->items->first()->product_id;
        $this->send($this->event($order, 'evt_failed_01', 'checkout.session.async_payment_failed', 'unpaid'))
            ->assertOk();
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'payment_failed']);
    }

    public function test_expiration_after_paid_does_not_restore_stock(): void
    {
        $order = $this->order();
        $productId = $order->items->first()->product_id;
        $this->send($this->event($order, 'evt_firstpaid_01', 'checkout.session.completed'))
            ->assertOk();
        $this->send($this->event($order, 'evt_lateexpired_01', 'checkout.session.expired', 'unpaid'))
            ->assertOk();
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_mismatched_amount_or_session_cannot_mark_paid(): void
    {
        $order = $this->order();
        $wrongAmount = $this->event($order, 'evt_wrongamt_01', 'checkout.session.completed');
        $wrongAmount['data']['object']['amount_total'] = 1;
        $this->send($wrongAmount)->assertStatus(409);

        $wrongSession = $this->event($order, 'evt_wrongsession_01', 'checkout.session.completed');
        $wrongSession['data']['object']['id'] = 'cs_test_other';
        $this->send($wrongSession)->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_unrelated_stripe_event_is_ignored(): void
    {
        $this->send(['id' => 'evt_other_01', 'object' => 'event',
            'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]])
            ->assertOk()->assertJsonPath('message', 'Evento ignorado.');
    }
}
