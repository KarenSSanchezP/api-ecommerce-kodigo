<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    private const TYPES = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['success' => false, 'message' => 'Webhook no configurado.'], 503);
        }

        // No decodificar ni volver a codificar el JSON antes de verificar la firma.
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (UnexpectedValueException | SignatureVerificationException $exception) {
            return response()->json(['success' => false, 'message' => 'Webhook inválido.'], 400);
        }

        if (! in_array($event->type, self::TYPES, true)) {
            return response()->json(['success' => true, 'message' => 'Evento ignorado.']);
        }

        $session = $event->data->object;
        $sessionId = $session->id ?? null;
        $reference = (string) ($session->client_reference_id ?? '');
        $metadataOrderId = (string) ($session->metadata->order_id ?? '');
        $eventId = $event->id ?? null;

        if (($session->object ?? null) !== 'checkout.session'
            || ! is_string($sessionId) || ! str_starts_with($sessionId, 'cs_')
            || ! ctype_digit($reference) || $reference === '0'
            || $metadataOrderId !== $reference
            || ! is_string($eventId) || ! str_starts_with($eventId, 'evt_')) {
            return response()->json(['success' => false, 'message' => 'Datos de sesión inválidos.'], 400);
        }

        $result = DB::transaction(function () use ($event, $session, $sessionId, $reference, $eventId): string {
            // Primero bloquear la orden; esto serializa entregas simultáneas del mismo pago.
            $order = Order::query()->whereKey((int) $reference)->lockForUpdate()->first();
            if (! $order) {
                Log::warning('Webhook Stripe para orden no encontrada.', ['event_id' => $eventId]);
                return 'ignored';
            }

            $payment = $order->payment()->lockForUpdate()->first();
            if (! $payment || $payment->stripe_checkout_session_id !== $sessionId
                || $payment->provider !== 'stripe') {
                Log::warning('Webhook Stripe no coincide con la sesión registrada.', [
                    'event_id' => $eventId, 'order_id' => $order->id,
                ]);
                // Stripe puede reintentar cuando la sesión aún no fue persistida.
                return 'retry';
            }

            if (DB::table('stripe_webhook_events')->where('stripe_event_id', $eventId)->exists()) {
                return 'duplicate';
            }

            if (! is_int($session->amount_total ?? null)
                || $session->amount_total !== (int) $order->total_cents
                || $session->amount_total !== (int) $payment->amount_cents
                || strtolower((string) ($session->currency ?? '')) !== strtolower($order->currency)) {
                Log::error('Monto o moneda inconsistente en Stripe.', [
                    'event_id' => $eventId, 'order_id' => $order->id,
                ]);
                return 'retry';
            }

            $type = $event->type;
            $isPaid = $type === 'checkout.session.async_payment_succeeded'
                || ($type === 'checkout.session.completed' && ($session->payment_status ?? null) === 'paid');
            $isExpired = $type === 'checkout.session.expired';
            $isFailed = $type === 'checkout.session.async_payment_failed';

            if ($isPaid && ! in_array($order->status, ['pending', 'paid'], true)) {
                // Importante: no confirmar automáticamente pagos de órdenes cuyo stock se devolvió.
                Log::critical('Pago recibido para orden cerrada: requiere revisión manual.', [
                    'order_id' => $order->id, 'event_id' => $eventId,
                ]);
                return 'retry';
            }

            if ($isPaid && $order->status === 'pending') {
                $paymentIntent = $session->payment_intent ?? null;
                $paymentIntentId = is_string($paymentIntent) ? $paymentIntent : ($paymentIntent->id ?? null);

                $order->update(['status' => 'paid']);
                $payment->update([
                    'status' => 'paid',
                    'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
                ]);
            } elseif (($isExpired || $isFailed) && $order->status === 'pending') {
                // El stock se reservó al crear la orden: devolverlo UNA sola vez.
                $items = $order->items()->orderBy('product_id')->get();
                foreach ($items as $item) {
                    Product::query()->whereKey($item->product_id)
                        ->lockForUpdate()->firstOrFail()
                        ->increment('stock', $item->quantity);
                }
                $order->update(['status' => $isExpired ? 'expired' : 'payment_failed']);
                $payment->update(['status' => $isExpired ? 'expired' : 'failed']);
            }
            // En 'completed' con payment_status=unpaid no se confirma el pago:
            // debemos esperar async_payment_succeeded o async_payment_failed.

            DB::table('stripe_webhook_events')->insert([
                'stripe_event_id' => $eventId,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 'processed';
        }, 3);

        if ($result === 'retry') {
            return response()->json(['success' => false, 'message' => 'Evento pendiente de conciliación.'], 409);
        }

        return response()->json(['success' => true, 'message' => $result]);
    }
}
