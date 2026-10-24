<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'OrderLineInput', type: 'object', required: ['product_id', 'quantity'], properties: [
    new OA\Property(property: 'product_id', type: 'integer', example: 1),
    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 100, example: 2),
])]
#[OA\Schema(schema: 'OrderCreateInput', type: 'object', required: ['items'], properties: [
    new OA\Property(property: 'items', type: 'array', minItems: 1, maxItems: 20, items: new OA\Items(ref: '#/components/schemas/OrderLineInput')),
])]
class OrderSpec
{
    #[OA\Get(
        path: '/api/v1/orders',
        summary: 'Listar únicamente las órdenes del cliente autenticado',
        tags: ['Ordenes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado de órdenes'),
            new OA\Response(response: 401, description: 'JWT requerido'),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/v1/orders',
        summary: 'Crear orden; calcular total y reservar stock dentro de una transacción',
        description: 'El cliente envía únicamente IDs y cantidades. El servidor consulta precios, calcula importes y reserva existencias. La orden y el pago quedan pending hasta confirmación de Stripe.',
        tags: ['Ordenes'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/OrderCreateInput')),
        responses: [
            new OA\Response(response: 201, description: 'Orden y pago pendientes creados'),
            new OA\Response(response: 401, description: 'JWT requerido'),
            new OA\Response(response: 422, description: 'Datos inválidos o stock insuficiente'),
        ]
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/v1/orders/{order}',
        summary: 'Ver una orden propia',
        tags: ['Ordenes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Orden con artículos y pago'),
            new OA\Response(response: 401, description: 'JWT requerido'),
            new OA\Response(response: 404, description: 'Orden inexistente o ajena'),
        ]
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/api/v1/orders/{order}/cancel',
        summary: 'Cancelar una orden pendiente y restaurar el inventario',
        description: 'No se puede cancelar tras iniciar una sesión Stripe Checkout. Los estados se actualizan con transacción de base de datos.',
        tags: ['Ordenes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Orden cancelada; stock restaurado'),
            new OA\Response(response: 401, description: 'JWT requerido'),
            new OA\Response(response: 404, description: 'Orden ajena o inexistente'),
            new OA\Response(response: 409, description: 'No se puede cancelar en este estado'),
        ]
    )]
    public function cancel(): void {}
}
