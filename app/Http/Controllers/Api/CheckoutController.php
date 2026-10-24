<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\StripeCheckoutGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

class CheckoutController extends Controller
{
    public function store(Request $request, Order $order, StripeCheckoutGateway $gateway): JsonResponse
    {
        // No confirmar la existencia de órdenes pertenecientes a otros clientes.
        abort_unless((int) $order->user_id === (int) $request->user('api')->id, 404);

        // Permite desplegar, ejecutar pruebas y usar el catálogo sin cuenta Stripe.
        if (! config('services.stripe.secret')) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe Checkout no está configurado en este entorno.',
            ], 503);
        }

        try {
            return DB::transaction(function () use ($order, $request, $gateway): JsonResponse {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedOrder->user_id === (int) $request->user('api')->id, 404);

                if ($lockedOrder->status !== 'pending') {
                    return $this->conflict('La orden ya no está pendiente de pago.');
                }

                $payment = $lockedOrder->payment()->lockForUpdate()->first();
                if (! $payment || ! in_array($payment->status, ['pending', 'checkout_pending'], true)) {
                    return $this->conflict('El pago no está disponible para Checkout.');
                }

                $isNewSession = $payment->stripe_checkout_session_id === null;
                if ($isNewSession) {
                    $session = $gateway->create($lockedOrder);
                } else {
                    // Reutilizar la sesión existente: no crear otro posible cobro.
                    $session = $gateway->retrieve($payment->stripe_checkout_session_id);
                }

                if (($session['status'] ?? null) !== 'open' || ! is_string($session['url'] ?? null)
                    || ! str_starts_with($session['url'], 'https://checkout.stripe.com/')
                    || ! is_string($session['id'] ?? null)) {
                    return $this->conflict('La sesión de Checkout no está disponible.');
                }

                if ($isNewSession) {
                    $payment->update([
                        'stripe_checkout_session_id' => $session['id'],
                        'status' => 'checkout_pending',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $lockedOrder->id,
                        'checkout_session_id' => $session['id'],
                        'checkout_url' => $session['url'],
                        'payment_status' => $payment->status,
                    ],
                    'message' => 'Abre checkout_url para pagar. El pago se confirma mediante webhook.',
                ]);
            }, 3);
        } catch (ApiErrorException $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'No fue posible comunicarse con Stripe. Intenta nuevamente.',
            ], 502);
        }
    }

    public function success(): JsonResponse
    {
        // Una redirección jamás confirma el pago: solo el webhook verificado puede hacerlo.
        return response()->json([
            'success' => true,
            'message' => 'Checkout finalizado. El pago se confirmará mediante un webhook verificado.',
        ]);
    }

    public function cancel(): JsonResponse
    {
        // Regresar a esta URL no cancela la orden ni devuelve stock.
        return response()->json([
            'success' => true,
            'message' => 'Regresaste de Checkout. La orden no se ha cancelado automáticamente.',
        ]);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 409);
    }
}
