<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class CheckoutSpec
{
    #[OA\Post(path: '/api/v1/orders/{order}/checkout', summary: 'Crear o recuperar Stripe Checkout de una orden pendiente propia', description: 'No enviar precios. El servidor utiliza el total de la orden. Requiere STRIPE_SECRET_KEY de test o live valido. La redireccion nunca confirma el cobro; el webhook validado es la unica fuente de confirmacion.', tags: ['Pagos'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: 'URL de Checkout', content: new OA\JsonContent(ref: '#/components/schemas/CheckoutResponse')),
        new OA\Response(response: 401, description: 'JWT requerido'),
        new OA\Response(response: 404, description: 'Orden ajena o inexistente'),
        new OA\Response(response: 409, description: 'Sesion/orden no disponible', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
        new OA\Response(response: 429, description: 'Limite de solicitudes'),
        new OA\Response(response: 502, description: 'Error externo de Stripe'),
        new OA\Response(response: 503, description: 'Stripe sin configurar'),
    ])]
    public function createCheckout(): void {}

    #[OA\Get(path: '/api/v1/checkout/success', summary: 'URL informativa tras Checkout: NO confirma pago', tags: ['Pagos'], parameters: [
        new OA\Parameter(name: 'session_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
    ], responses: [new OA\Response(response: 200, description: 'Respuesta informativa', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse'))])]
    public function success(): void {}

    #[OA\Get(path: '/api/v1/checkout/cancel', summary: 'URL de regreso de Checkout: NO cancela orden', tags: ['Pagos'], responses: [
        new OA\Response(response: 200, description: 'Respuesta informativa', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
    ])]
    public function cancel(): void {}
}
