<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RegisterInput', type: 'object', required: ['name','email','password','password_confirmation'], properties: [
    new OA\Property(property: 'name', type: 'string', example: 'Cliente Demo'),
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.test'),
    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password123!'),
    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Password123!'),
])]
#[OA\Schema(schema: 'LoginInput', type: 'object', required: ['email', 'password'], properties: [
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.test'),
    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password123!'),
])]
#[OA\Schema(schema: 'ProductInput', type: 'object', required: ['sku', 'name', 'price_cents', 'stock'], properties: [
    new OA\Property(property: 'sku', type: 'string', maxLength: 64, example: 'TEC-006'),
    new OA\Property(property: 'name', type: 'string', example: 'Cargador USB-C'),
    new OA\Property(property: 'description', type: 'string', nullable: true, example: '65W'),
    new OA\Property(property: 'price_cents', type: 'integer', minimum: 1, example: 2599),
    new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 15),
    new OA\Property(property: 'is_active', type: 'boolean', example: true),
])]
#[OA\Schema(schema: 'ProductPatchInput', type: 'object', properties: [
    new OA\Property(property: 'sku', type: 'string', maxLength: 64, example: 'TEC-006'),
    new OA\Property(property: 'name', type: 'string', example: 'Cargador USB-C mejorado'),
    new OA\Property(property: 'description', type: 'string', nullable: true, example: '65W'),
    new OA\Property(property: 'price_cents', type: 'integer', minimum: 1, example: 2499),
    new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 18),
    new OA\Property(property: 'is_active', type: 'boolean', example: true),
])]
#[OA\Schema(schema: 'OrderLineInput', type: 'object', required: ['product_id', 'quantity'], properties: [
    new OA\Property(property: 'product_id', type: 'integer', example: 1),
    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 100, example: 2),
])]
#[OA\Schema(schema: 'OrderCreateInput', type: 'object', required: ['items'], properties: [
    new OA\Property(property: 'items', type: 'array', minItems: 1, maxItems: 20, items: new OA\Items(ref: '#/components/schemas/OrderLineInput')),
])]
#[OA\Schema(schema: 'PublicUser', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'name', type: 'string', example: 'Cliente Demo'),
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.test'),
    new OA\Property(property: 'is_admin', type: 'boolean', example: false),
])]
#[OA\Schema(schema: 'JwtToken', type: 'object', properties: [
    new OA\Property(property: 'access_token', type: 'string', description: 'JWT firmado'),
    new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
    new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
])]
#[OA\Schema(schema: 'JwtTokenResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'data', ref: '#/components/schemas/JwtToken'),
])]
#[OA\Schema(schema: 'UserResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'data', ref: '#/components/schemas/PublicUser'),
])]
#[OA\Schema(schema: 'RegistrationResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'data', ref: '#/components/schemas/PublicUser'),
])]
#[OA\Schema(schema: 'Product', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'sku', type: 'string', example: 'TEC-001'),
    new OA\Property(property: 'name', type: 'string', example: 'Mouse inalámbrico'),
    new OA\Property(property: 'description', type: 'string', nullable: true),
    new OA\Property(property: 'price_cents', type: 'integer', example: 1599),
    new OA\Property(property: 'stock', type: 'integer', example: 30),
    new OA\Property(property: 'is_active', type: 'boolean', example: true),
])]
#[OA\Schema(schema: 'ProductResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
])]
#[OA\Schema(schema: 'ProductPage', type: 'object', properties: [
    new OA\Property(property: 'current_page', type: 'integer', example: 1),
    new OA\Property(property: 'per_page', type: 'integer', example: 10),
    new OA\Property(property: 'total', type: 'integer', example: 5),
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
])]
#[OA\Schema(schema: 'ProductPageResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'data', ref: '#/components/schemas/ProductPage'),
])]
#[OA\Schema(schema: 'OrderLine', type: 'object', properties: [
    new OA\Property(property: 'product_id', type: 'integer'),
    new OA\Property(property: 'name_snapshot', type: 'string'),
    new OA\Property(property: 'unit_price_cents', type: 'integer'),
    new OA\Property(property: 'quantity', type: 'integer'),
    new OA\Property(property: 'subtotal_cents', type: 'integer'),
])]
#[OA\Schema(schema: 'OrderPayment', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'provider', type: 'string', example: 'stripe'),
    new OA\Property(property: 'status', type: 'string', example: 'pending'),
    new OA\Property(property: 'amount_cents', type: 'integer'),
    new OA\Property(property: 'stripe_checkout_session_id', type: 'string', nullable: true),
    new OA\Property(property: 'stripe_payment_intent_id', type: 'string', nullable: true),
])]
#[OA\Schema(schema: 'Order', type: 'object', properties: [
    new OA\Property(property: 'id', type: 'integer'),
    new OA\Property(property: 'user_id', type: 'integer'),
    new OA\Property(property: 'status', type: 'string', example: 'pending'),
    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    new OA\Property(property: 'total_cents', type: 'integer', example: 3198),
    new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderLine')),
    new OA\Property(property: 'payment', ref: '#/components/schemas/OrderPayment'),
])]
#[OA\Schema(schema: 'OrderResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'data', ref: '#/components/schemas/Order'),
])]
#[OA\Schema(schema: 'OrderPage', type: 'object', properties: [
    new OA\Property(property: 'current_page', type: 'integer', example: 1),
    new OA\Property(property: 'per_page', type: 'integer', example: 10),
    new OA\Property(property: 'total', type: 'integer', example: 1),
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
])]
#[OA\Schema(schema: 'OrderPageResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'data', ref: '#/components/schemas/OrderPage'),
])]
#[OA\Schema(schema: 'CheckoutPayload', type: 'object', properties: [
    new OA\Property(property: 'order_id', type: 'integer'),
    new OA\Property(property: 'checkout_session_id', type: 'string', example: 'cs_test_...'),
    new OA\Property(property: 'checkout_url', type: 'string', format: 'uri', example: 'https://checkout.stripe.com/c/pay/...'),
    new OA\Property(property: 'payment_status', type: 'string', example: 'checkout_pending'),
])]
#[OA\Schema(schema: 'CheckoutResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'message', type: 'string'),
    new OA\Property(property: 'data', ref: '#/components/schemas/CheckoutPayload'),
])]
#[OA\Schema(schema: 'StatusResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: true),
    new OA\Property(property: 'message', type: 'string'),
])]
#[OA\Schema(schema: 'ApiErrorResponse', type: 'object', properties: [
    new OA\Property(property: 'success', type: 'boolean', example: false),
    new OA\Property(property: 'message', type: 'string'),
])]
#[OA\Schema(schema: 'ValidationErrorResponse', type: 'object', properties: [
    new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
    new OA\Property(property: 'errors', type: 'object', description: 'Errores por atributo'),
])]
class ComponentSchemas
{
}
