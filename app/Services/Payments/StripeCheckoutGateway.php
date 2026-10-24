<?php

namespace App\Services\Payments;

use App\Models\Order;
use Stripe\StripeClient;

class StripeCheckoutGateway
{
    /**
     * @return array{id: string, url: ?string, status: string}
     */
    public function create(Order $order): array
    {
        $order->loadMissing(['items', 'user']);

        $client = new StripeClient(config('services.stripe.secret'));

        $lineItems = $order->items->map(fn ($item): array => [
            'price_data' => [
                'currency' => strtolower($order->currency),
                'unit_amount' => $item->unit_price_cents,
                'product_data' => ['name' => $item->name_snapshot],
            ],
            'quantity' => $item->quantity,
        ])->all();

        $baseUrl = rtrim(config('app.url'), '/');

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $order->user->email,
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent_data' => [
                'metadata' => ['order_id' => (string) $order->id],
            ],
            'success_url' => $baseUrl.'/api/v1/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl.'/api/v1/checkout/cancel',
            // Una hora para completar el pago. Hito 9: liberar stock en checkout.session.expired.
            'expires_at' => time() + 3600,
        ], [
            'idempotency_key' => 'kodigo-checkout-'.substr(hash('sha256', (string) config('app.key')), 0, 16).'-'.$order->id,
        ]);

        return [
            'id' => $session->id,
            'url' => $session->url,
            'status' => $session->status,
        ];
    }

    /** @return array{id: string, url: ?string, status: string} */
    public function retrieve(string $sessionId): array
    {
        $client = new StripeClient(config('services.stripe.secret'));
        $session = $client->checkout->sessions->retrieve($sessionId, []);

        return [
            'id' => $session->id,
            'url' => $session->url,
            'status' => $session->status,
        ];
    }
}
