<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user('api')->id)
            ->with(['items', 'payment'])
            ->latest('id')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        // No revelar si existen compras de otros usuarios.
        abort_unless((int) $order->user_id === (int) $request->user('api')->id, 404);

        return response()->json([
            'success' => true,
            'data' => $order->load(['items', 'payment']),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($request, $data): Order {
            $entries = collect($data['items'])->keyBy('product_id');
            $products = Product::query()
                ->whereIn('id', $entries->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $items = [];
            $totalCents = 0;

            foreach ($entries as $productId => $entry) {
                $product = $products->get($productId);
                $quantity = (int) $entry['quantity'];

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos no está disponible.',
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para el producto {$product->sku}.",
                    ]);
                }

                $subtotalCents = $product->price_cents * $quantity;
                $totalCents += $subtotalCents;

                $items[] = [
                    'product_id' => $product->id,
                    'name_snapshot' => $product->name,
                    'unit_price_cents' => $product->price_cents,
                    'quantity' => $quantity,
                    'subtotal_cents' => $subtotalCents,
                ];
            }

            $order = Order::create([
                'user_id' => $request->user('api')->id,
                'status' => 'pending',
                'total_cents' => $totalCents,
                'currency' => 'USD',
            ]);

            $order->items()->createMany($items);
            $order->payment()->create([
                'provider' => 'stripe',
                'status' => 'pending',
                'amount_cents' => $totalCents,
            ]);

            // Reservamos unidades al crear la orden; se reponen al cancelar.
            foreach ($items as $item) {
                $products->get($item['product_id'])->decrement('stock', $item['quantity']);
            }

            return $order;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Orden creada. Inventario reservado; pago pendiente.',
            'data' => $order->load(['items', 'payment']),
        ], 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        return DB::transaction(function () use ($request, $order): JsonResponse {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedOrder->user_id === (int) $request->user('api')->id,
                404
            );

            if ($lockedOrder->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede cancelar una orden pendiente.',
                ], 409);
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment?->stripe_checkout_session_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'El checkout ya comenzó. Su cancelación debe gestionarse con Stripe.',
                ], 409);
            }

            $lines = $lockedOrder->items()->orderBy('product_id')->get();
            foreach ($lines as $line) {
                Product::query()
                    ->whereKey($line->product_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->increment('stock', $line->quantity);
            }

            $lockedOrder->update(['status' => 'cancelled']);
            $payment?->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Orden cancelada e inventario restaurado.',
                'data' => $lockedOrder->load(['items', 'payment']),
            ]);
        }, 3);
    }
}
