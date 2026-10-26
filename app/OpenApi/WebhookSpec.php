<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class WebhookSpec
{
    #[OA\Post(
        path: '/api/v1/webhooks/stripe',
        summary: 'Recibir y verificar eventos Stripe Checkout',
        description: 'Webhook público para Stripe (NO utiliza JWT). Requiere cabecera Stripe-Signature válida y STRIPE_WEBHOOK_SECRET. Procesa eventos completed, async_payment_succeeded, async_payment_failed y expired. Respuestas 409 se reintentan, sin guardar eventos inconsistentes.',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'Stripe-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(type: 'object'))),
        responses: [
            new OA\Response(response: 200, description: 'Evento procesado, repetido o ignorado'),
            new OA\Response(response: 400, description: 'Firma o contenido inválido'),
            new OA\Response(response: 409, description: 'Sesión incongruente; reintentar'),
            new OA\Response(response: 503, description: 'STRIPE_WEBHOOK_SECRET no configurado'),
        ]
    )]
    public function stripe(): void {}
}
