<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(title: 'KODIGO E-commerce API', version: '1.0.0', description: 'API REST Laravel 12, JWT, MySQL, Stripe Checkout y webhooks. Importes en centavos de USD. Entorno local Docker.')]
#[OA\Server(url: 'http://127.0.0.1:8091', description: 'Docker / Nginx local')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Tag(name: 'Autenticacion', description: 'Registro, login y JWT')]
#[OA\Tag(name: 'Productos', description: 'Catalogo publico y administracion protegida')]
#[OA\Tag(name: 'Ordenes', description: 'Historial, reserva de inventario y cancelacion')]
#[OA\Tag(name: 'Pagos', description: 'Stripe Checkout, sin confirmar pagos por redireccion')]
#[OA\Tag(name: 'Webhooks', description: 'Notificaciones verificadas de Stripe')]
class ApiSpec
{
    #[OA\Post(path: '/api/v1/auth/register', summary: 'Registrar un cliente (nunca administrador)', tags: ['Autenticacion'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInput')), responses: [
        new OA\Response(response: 201, description: 'Cliente registrado', content: new OA\JsonContent(ref: '#/components/schemas/RegistrationResponse')),
        new OA\Response(response: 422, description: 'Validacion fallida', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        new OA\Response(response: 429, description: 'Demasiadas solicitudes'),
    ])]
    public function register(): void {}

    #[OA\Post(path: '/api/v1/auth/login', summary: 'Iniciar sesion y obtener JWT', tags: ['Autenticacion'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginInput')), responses: [
        new OA\Response(response: 200, description: 'Token JWT', content: new OA\JsonContent(ref: '#/components/schemas/JwtTokenResponse')),
        new OA\Response(response: 401, description: 'Credenciales invalidas', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
        new OA\Response(response: 422, description: 'Validacion fallida', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        new OA\Response(response: 429, description: 'Demasiadas solicitudes'),
    ])]
    public function login(): void {}

    #[OA\Get(path: '/api/v1/auth/me', summary: 'Perfil autenticado', tags: ['Autenticacion'], security: [['bearerAuth' => []]], responses: [
        new OA\Response(response: 200, description: 'Perfil', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
        new OA\Response(response: 401, description: 'JWT requerido', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
    ])]
    public function me(): void {}

    #[OA\Post(path: '/api/v1/auth/logout', summary: 'Cerrar sesion / invalidar JWT', tags: ['Autenticacion'], security: [['bearerAuth' => []]], responses: [
        new OA\Response(response: 200, description: 'Sesion cerrada', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        new OA\Response(response: 401, description: 'JWT requerido', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
    ])]
    public function logout(): void {}

    #[OA\Get(path: '/api/v1/products', summary: 'Catalogo activo paginado', tags: ['Productos'], parameters: [
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
    ], responses: [new OA\Response(response: 200, description: 'Listado', content: new OA\JsonContent(ref: '#/components/schemas/ProductPageResponse'))])]
    public function products(): void {}

    #[OA\Get(path: '/api/v1/products/{product}', summary: 'Detalle de producto activo', tags: ['Productos'], parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: 'Producto', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(response: 404, description: 'Producto no encontrado o inactivo'),
    ])]
    public function product(): void {}

    #[OA\Post(path: '/api/v1/products', summary: 'Crear producto (administrador)', tags: ['Productos'], security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')), responses: [
        new OA\Response(response: 201, description: 'Creado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No autorizado'),
        new OA\Response(response: 422, description: 'Datos invalidos o SKU repetido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
    ])]
    public function storeProduct(): void {}

    #[OA\Put(path: '/api/v1/products/{product}', summary: 'Actualizar producto (administrador)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductPatchInput')), responses: [
        new OA\Response(response: 200, description: 'Actualizado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No autorizado'),
        new OA\Response(response: 404, description: 'No encontrado'), new OA\Response(response: 422, description: 'Validacion fallida', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
    ])]
    public function updateProduct(): void {}

    #[OA\Patch(path: '/api/v1/products/{product}', summary: 'Actualizacion parcial (administrador)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductPatchInput')), responses: [
        new OA\Response(response: 200, description: 'Actualizado', content: new OA\JsonContent(ref: '#/components/schemas/ProductResponse')),
        new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No autorizado'),
        new OA\Response(response: 404, description: 'No encontrado'), new OA\Response(response: 422, description: 'Validacion fallida', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
    ])]
    public function patchProduct(): void {}

    #[OA\Delete(path: '/api/v1/products/{product}', summary: 'Desactivar producto (administrador, borrado logico)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: 'Desactivado', content: new OA\JsonContent(ref: '#/components/schemas/StatusResponse')),
        new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No autorizado'),
        new OA\Response(response: 404, description: 'No encontrado'),
    ])]
    public function deleteProduct(): void {}
}
