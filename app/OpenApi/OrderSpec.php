<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class OrderSpec
{
    #[OA\Get(path: '/api/v1/orders', summary: 'Listar las ordenes propias (paginado)', tags: ['Ordenes'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
    ], responses: [
        new OA\Response(response: 200, description: 'Ordenes propias', content: new OA\JsonContent(ref: '#/components/schemas/OrderPageResponse')),
        new OA\Response(response: 401, description: 'No autenticado'),
    ])]
    public function index(): void {}

    #[OA\Post(path: '/api/v1/orders', summary: 'Crear orden con reserva de inventario', description: 'Enviar exclusivamente product_id y quantity. Precios, totales, estado y usuario se calculan en servidor. La orden permanece pending hasta el webhook de Stripe.', tags: ['Ordenes'], security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/OrderCreateInput')), responses: [
        new OA\Response(response: 201, description: 'Orden creada', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        new OA\Response(response: 401, description: 'No autenticado'),
        new OA\Response(response: 422, description: 'Stock insuficiente o datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
    ])]
    public function store(): void {}

    #[OA\Get(path: '/api/v1/orders/{order}', summary: 'Obtener detalle de una orden propia', tags: ['Ordenes'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: 'Orden', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        new OA\Response(response: 401, description: 'No autenticado'),
        new OA\Response(response: 404, description: 'Orden inexistente o ajena'),
    ])]
    public function show(): void {}

    #[OA\Post(path: '/api/v1/orders/{order}/cancel', summary: 'Cancelar orden propia pendiente y devolver existencias', description: 'La cancelacion local solo funciona antes de iniciar una sesion Stripe Checkout.', tags: ['Ordenes'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: 'Orden cancelada', content: new OA\JsonContent(ref: '#/components/schemas/OrderResponse')),
        new OA\Response(response: 401, description: 'No autenticado'),
        new OA\Response(response: 404, description: 'Orden ajena o inexistente'),
        new OA\Response(response: 409, description: 'Cancelacion no permitida', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
    ])]
    public function cancel(): void {}
}
