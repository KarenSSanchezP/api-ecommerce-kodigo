<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(title: 'KODIGO E-commerce API', version: '1.0.0', description: 'Laravel 12, JWT y productos. Ordenes y pagos: proximo hito.')]
#[OA\Server(url: 'http://127.0.0.1:8091', description: 'Servidor Docker local')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Schema(schema: 'RegisterInput', type: 'object', required: ['name', 'email', 'password', 'password_confirmation'], properties: [
    new OA\Property(property: 'name', type: 'string', example: 'Cliente Demo'),
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.test'),
    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'ClaveDemoSegura123!'),
    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'ClaveDemoSegura123!'),
])]
#[OA\Schema(schema: 'LoginInput', type: 'object', required: ['email', 'password'], properties: [
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'cliente@example.test'),
    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'ClaveDemoSegura123!'),
])]
#[OA\Schema(schema: 'ProductInput', type: 'object', required: ['sku', 'name', 'price_cents', 'stock'], properties: [
    new OA\Property(property: 'sku', type: 'string', example: 'TEC-006'),
    new OA\Property(property: 'name', type: 'string', example: 'Cargador USB-C'),
    new OA\Property(property: 'description', type: 'string', nullable: true, example: '65W'),
    new OA\Property(property: 'price_cents', type: 'integer', minimum: 1, example: 2599),
    new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 15),
    new OA\Property(property: 'is_active', type: 'boolean', example: true),
])]
class ApiSpec
{
    #[OA\Post(path: '/api/v1/auth/register', summary: 'Registro de clientes', tags: ['Autenticacion'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInput')), responses: [new OA\Response(response: 201, description: 'Registrado'), new OA\Response(response: 422, description: 'Datos invalidos')])]
    public function register(): void {}

    #[OA\Post(path: '/api/v1/auth/login', summary: 'Obtener JWT', tags: ['Autenticacion'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginInput')), responses: [new OA\Response(response: 200, description: 'Token JWT'), new OA\Response(response: 401, description: 'Credenciales invalidas')])]
    public function login(): void {}

    #[OA\Get(path: '/api/v1/auth/me', summary: 'Perfil autenticado', tags: ['Autenticacion'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Perfil'), new OA\Response(response: 401, description: 'No autenticado')])]
    public function me(): void {}

    #[OA\Post(path: '/api/v1/auth/logout', summary: 'Cerrar sesion', tags: ['Autenticacion'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Sesion cerrada'), new OA\Response(response: 401, description: 'No autenticado')])]
    public function logout(): void {}

    #[OA\Get(path: '/api/v1/products', summary: 'Catalogo publico activo (paginado)', tags: ['Productos'], responses: [new OA\Response(response: 200, description: 'Catalogo')])]
    public function products(): void {}

    #[OA\Get(path: '/api/v1/products/{product}', summary: 'Detalle publico del producto', tags: ['Productos'], parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Producto'), new OA\Response(response: 404, description: 'No encontrado')])]
    public function product(): void {}

    #[OA\Post(path: '/api/v1/products', summary: 'Crear producto (admin)', tags: ['Productos'], security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')), responses: [new OA\Response(response: 201, description: 'Creado'), new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No es administrador'), new OA\Response(response: 422, description: 'Validacion fallida')])]
    public function storeProduct(): void {}

    #[OA\Put(path: '/api/v1/products/{product}', summary: 'Actualizar producto (admin)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')), responses: [new OA\Response(response: 200, description: 'Actualizado'), new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No es administrador'), new OA\Response(response: 422, description: 'Validacion fallida')])]
    public function updateProduct(): void {}

    #[OA\Patch(path: '/api/v1/products/{product}', summary: 'Actualizacion parcial (admin)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')), responses: [new OA\Response(response: 200, description: 'Actualizado'), new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No es administrador'), new OA\Response(response: 422, description: 'Validacion fallida')])]
    public function patchProduct(): void {}

    #[OA\Delete(path: '/api/v1/products/{product}', summary: 'Desactivar producto sin borrarlo (admin)', tags: ['Productos'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Desactivado'), new OA\Response(response: 401, description: 'No autenticado'), new OA\Response(response: 403, description: 'No es administrador'), new OA\Response(response: 404, description: 'No encontrado')])]
    public function deleteProduct(): void {}
}
