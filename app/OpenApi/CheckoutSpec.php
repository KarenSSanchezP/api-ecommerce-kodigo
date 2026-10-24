<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class CheckoutSpec
{
    #[OA\Post(
        path: '/api/v1/orders/{order}/checkout',
        summary: 'Crear o recuperar Stripe Checkout para una orden propia pendiente',
        description: 'No envíes precios desde el cliente. El servidor usa los importes congelados en order_items. Necesita STRIPE_SECRET_KEY de prueba. El checkout nunca marca una orden como pagada; solo lo hará un webhook verificado.',
        tags: ['Pagos'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'URL de Stripe Checkout para redirigir al cliente'),
            new OA\Response(response: 401, description: 'JWT requerido'),
            new OA\Response(response: 404, description: 'Orden no encontrada o de otro cliente'),
            new OA\Response(response: 409, description: 'Orden o sesión no disponible'),
            new OA\Response(response: 502, description: 'Error al contactar Stripe'),
            new OA\Response(response: 503, description: 'Falta configuración Stripe'),
        ]
    )]
    public function createCheckout(): void {}

    #[OA\Get(path: '/api/v1/checkout/success', tags: ['Pagos'], summary: 'Redirección tras Checkout (no confirma el pago)', responses: [new OA\Response(response: 200, description: 'Mensaje informativo')])]
    public function success(): void {}

    #[OA\Get(path: '/api/v1/checkout/cancel', tags: ['Pagos'], summary: 'Redirección de regreso (no cancela la orden)', responses: [new OA\Response(response: 200, description: 'Mensaje informativo')])]
    public function cancel(): void {}
}
