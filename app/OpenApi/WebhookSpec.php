<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class WebhookSpec
{
    #[OA\Post(path: '/api/v1/webhooks/stripe', summary: 'Webhook firmado de Stripe para confirmacion y caducidad', description: 'Endpoint publico, sin JWT: Stripe usa Stripe-Signature. Verifica el cuerpo original con stripe/stripe-php. Maneja completed, async_payment_succeeded, async_payment_failed y expired. Reintentos con HTTP 409 ante datos inconsistentes.', tags: ['Webhooks'], parameters: [
        new OA\Parameter(name: 'Stripe-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string'), description: 'Cabecera HMAC generada por Stripe'),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(type: 'object'))), responses: [
        new OA\Response(response: 200, description: 'Evento procesado, ignorado o repetido', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        new OA\Response(response: 400, description: 'Firma o evento invalido', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
        new OA\Response(response: 409, description: 'Necesita conciliacion/reintento', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
        new OA\Response(response: 429, description: 'Limite de solicitudes'),
        new OA\Response(response: 503, description: 'Secreto de webhook sin configurar', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
    ])]
    public function stripe(): void {}
}
