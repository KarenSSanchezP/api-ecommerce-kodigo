# API E-commerce Segura — KODIGO

API REST de comercio electrónico desarrollada con **Laravel 12**, **PHP 8.4**, **MySQL 8.4**, **JWT**, **Stripe Checkout** y documentación **OpenAPI / Swagger UI**. Proyecto académico Full Stack Jr., orientado a validar autenticación, control de acceso, transacciones de compra y pagos seguros.

## Tecnologías

- **Backend:** PHP 8.4, Laravel 12, Eloquent ORM, Form Requests.
- **Datos:** MySQL 8.4, migraciones, seeders, transacciones y bloqueos de filas.
- **Autenticación:** `php-open-source-saver/jwt-auth` con JWT y roles `customer/admin` (columna `is_admin`).
- **Pagos:** SDK oficial `stripe/stripe-php` 22; Stripe Checkout y webhooks firmados.
- **Documentación:** `darkaonline/l5-swagger` 11, atributos OpenAPI PHP.
- **Infraestructura:** Docker Compose, Nginx, PHP-FPM, MySQL; phpMyAdmin opcional.
- **Pruebas:** PHPUnit con SQLite en memoria para aislar la base de datos de desarrollo.

## Requisitos

Docker Desktop o Docker Engine con Compose y soporte para contenedores Linux; Git. **No necesitas instalar PHP, Composer, MySQL ni Nginx en tu computadora.**

## Instalación en Windows con PowerShell

Ejecuta desde una terminal PowerShell:

```powershell
git clone https://github.com/KarenSSanchezP/api-ecommerce-kodigo.git
cd api-ecommerce-kodigo
Copy-Item .env.example .env
notepad .env
```

Antes de levantar Docker, configura en `.env` dos contraseñas **locales y diferentes**:

```dotenv
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=kodigo_ecommerce
DB_USERNAME=ecommerce_dev
DB_PASSWORD=TU_CLAVE_LOCAL_MYSQL
MYSQL_ROOT_PASSWORD=OTRA_CLAVE_LOCAL_MYSQL
APP_URL=http://127.0.0.1:8091
```

Mantén `APP_KEY=` y `JWT_SECRET=` vacías por ahora; los próximos comandos las generan de manera segura. No publiques `.env` ni compartas sus claves.

```powershell
# Construir los contenedores de desarrollo
docker compose up -d --build

# Instalar dependencias dentro de PHP 8.4
docker compose exec app composer install

# Generar secretos en el .env local
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret

# Crear tablas y cargar cinco productos de demostracion
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Generar documentación OpenAPI
docker compose exec app php artisan l5-swagger:generate

# Verificar que los servicios están iniciados
docker compose ps
```

Abre las direcciones:

| Dirección | Uso |
|---|---|
| http://127.0.0.1:8091/up | Estado de salud de Laravel |
| http://127.0.0.1:8091/api/v1/products | Catálogo público JSON |
| http://127.0.0.1:8091/api/documentation | Swagger UI interactivo |

**phpMyAdmin opcional:**

```powershell
docker compose --profile tools up -d phpmyadmin
```

Abre http://127.0.0.1:8092 e inicia sesión con `DB_USERNAME` y `DB_PASSWORD`. El servidor de base de datos interno es `db`. Los puertos 8091 y 8092 se enlazan a `127.0.0.1`, no a toda la red.

### macOS / Linux

Sigue las mismas instrucciones, sustituyendo `Copy-Item .env.example .env` por `cp .env.example .env` y usando tu editor preferido. `docker compose` funciona igual.

> **Importante:** los comandos `php artisan`, `composer` y `phpunit` de este proyecto se ejecutan **dentro del contenedor**. `DB_HOST=db` se resuelve dentro de la red Docker y no es una dirección de Windows.

## API REST (v1)

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/api/v1/auth/register` | Público | Registro de cliente |
| POST | `/api/v1/auth/login` | Público | Obtener JWT |
| GET | `/api/v1/auth/me` | JWT | Perfil |
| POST | `/api/v1/auth/logout` | JWT | Invalidar token |
| GET | `/api/v1/products` | Público | Listar productos activos (paginación) |
| GET | `/api/v1/products/{product}` | Público | Detalle de producto activo |
| POST | `/api/v1/products` | Admin JWT | Crear producto |
| PUT/PATCH | `/api/v1/products/{product}` | Admin JWT | Actualizar producto |
| DELETE | `/api/v1/products/{product}` | Admin JWT | Desactivar producto (borrado lógico) |
| GET | `/api/v1/orders` | JWT | Órdenes propias |
| POST | `/api/v1/orders` | JWT | Crear orden y reservar inventario |
| GET | `/api/v1/orders/{order}` | JWT | Ver orden propia |
| POST | `/api/v1/orders/{order}/cancel` | JWT | Cancelar orden sin Checkout iniciado |
| POST | `/api/v1/orders/{order}/checkout` | JWT | Crear/recuperar sesión Stripe |
| GET | `/api/v1/checkout/success` | Público | Retorno informativo (NO confirma pago) |
| GET | `/api/v1/checkout/cancel` | Público | Retorno informativo (NO cancela orden) |
| POST | `/api/v1/webhooks/stripe` | Firma Stripe | Recibir webhooks (sin JWT) |

Todos los métodos excepto GET públicos y webhooks que requieren firma trabajan con JSON. Para endpoints JWT, utilizar `Authorization: Bearer <TOKEN>`.

## Flujo de compra

1. Registro e inicio de sesión del cliente.
2. `POST /orders` con un arreglo de `{product_id, quantity}`. La API **consulta precios en MySQL**, calcula el total en **centavos de USD** y reserva existencias con una transacción.
3. `POST /orders/{id}/checkout` solicita una sesión al SDK oficial de Stripe y devuelve `checkout_url`. Necesita credenciales de Stripe válidas.
4. El cliente paga mediante la página de Stripe, sin introducir datos de tarjeta en nuestra API.
5. El webhook firmado confirma el pago o libera inventario en expiraciones/fallos. **Visitar `/checkout/success` no cambia el estado del pago.**

El campo `stripe_webhook_events.stripe_event_id` impide reprocesar el mismo evento de Stripe. La lógica de orden también evita devolver stock dos veces ante eventos terminales distintos.

## Stripe: configuración opcional para modo de prueba

Añade credenciales válidas de **test mode** únicamente al archivo `.env` local:

```dotenv
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd
```

Vacías, la API permite utilizar catálogo, autenticación y órdenes, pero el endpoint de Checkout devuelve **503**. El webhook requiere `STRIPE_WEBHOOK_SECRET` configurado. Consulta [docs/PRUEBA_STRIPE.md](docs/PRUEBA_STRIPE.md) para un ensayo completo sin dinero real.

**Alcance verificado:** las pruebas automatizadas utilizan dobles de prueba para Stripe Checkout y eventos firmados generados localmente para el webhook. **No es evidencia de un pago real realizado a través de Stripe.**

## Pruebas y verificaciones

```powershell
docker compose exec app composer check-platform-reqs
docker compose exec app php artisan test
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list --path=api/v1
docker compose exec app php artisan l5-swagger:generate
```

PHPUnit utiliza SQLite en memoria; no ejecuta `migrate:fresh` sobre tu MySQL de desarrollo.

La documentación OpenAPI se genera automáticamente en `storage/api-docs` (ignorados por Git). Por eso es necesario ejecutar `l5-swagger:generate` después de clonar.

## Ejemplos de consumo, seguridad y limitaciones

- [Ejemplos PowerShell de registro, JWT y órdenes](docs/EJEMPLOS_API.md)
- [Auditoría de seguridad y cobertura de pruebas](docs/SEGURIDAD_Y_PRUEBAS.md)
- [Verificación de Stripe en modo de prueba](docs/PRUEBA_STRIPE.md)

Este repositorio es un **entorno de desarrollo académico**: no trae secretos, no almacena tarjetas y no debe desplegarse como sistema de cobro productivo sin endurecimiento adicional. Hay limitaciones relevantes documentadas, como reservas sin Checkout iniciado y pruebas de concurrencia real sobre MySQL pendientes.

## Comandos útiles

```powershell
docker compose ps
docker compose logs --tail=80 app
docker compose logs --tail=80 nginx
docker compose --profile tools up -d phpmyadmin
docker compose down
```

No uses `docker compose down -v` salvo que quieras **eliminar el volumen persistente de MySQL**.

## Autoría y propósito

Proyecto académico **API de E-commerce Segura con Swagger Completo** desarrollado para el programa Full Stack Jr. de KODIGO. Repositorio: [KarenSSanchezP/api-ecommerce-kodigo](https://github.com/KarenSSanchezP/api-ecommerce-kodigo).
