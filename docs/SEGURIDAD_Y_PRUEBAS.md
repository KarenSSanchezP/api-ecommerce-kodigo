# Seguridad y pruebas — API E-commerce KODIGO

## Cobertura automatizada

Ejecutar en Docker:

```powershell
docker compose exec app composer check-platform-reqs
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=PaymentLifecycleSecurityTest
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list --path=api/v1
```

PHPUnit está configurado con **SQLite en memoria** mediante `phpunit.xml`; no borra la base de datos MySQL de desarrollo.

## Controles implementados

- JWT para rutas autenticadas. Únicamente administradores pueden modificar el catálogo.
- Validación de Form Requests. `user_id`, precios y totales de órdenes se calculan en el servidor.
- Transacciones al reservar o devolver inventario y bloqueo de filas con `lockForUpdate` (MySQL).
- Órdenes visibles únicamente para su propietario.
- Integración con `stripe/stripe-php`. Secretos en `.env`, no en Git.
- Webhook público autenticado mediante **firma `Stripe-Signature`**, no JWT.
- Verificación del identificador de sesión, importe, moneda y orden al confirmar pagos.
- `stripe_webhook_events.stripe_event_id` único para controlar reentregas de eventos.
- Volver desde Checkout NO confirma un pago; solo una notificación Stripe validada puede hacerlo.
- PHPMyAdmin es opcional y está vinculado a `127.0.0.1` para desarrollo.

## Limitaciones que NO debemos ocultar

1. Las pruebas automatizadas de Checkout sustituyen el acceso externo mediante mocks. **No prueban una sesión real en Stripe**.
2. El test de concurrencia real requiere MySQL, varios procesos y un escenario de carga. SQLite en memoria no replica `SELECT ... FOR UPDATE` de MySQL; los tests actuales verifican la lógica transaccional, pero no demuestran por sí solos ausencia de carreras en producción.
3. Una orden `pending` que nunca inicia Checkout puede mantener inventario reservado indefinidamente. Es necesario un proceso de expiración/limpieza de reservas sin sesión para un entorno productivo.
4. Stripe puede entregar webhooks fuera de orden. Un pago que llegue después de una orden cuya reserva se liberó requiere conciliación manual. La API devuelve una respuesta de reintento y deja registro; no acredita inventario que ya fue devuelto.
5. Stripe solo estará disponible con credenciales válidas de un entorno legítimo. No se deben utilizar datos o cuentas falsos para habilitarlo.
6. La configuración Docker usa `php.ini-development`, `APP_DEBUG=true` en el ejemplo y montajes de código; es **de desarrollo**, no una configuración de producción endurecida.
7. El servicio de webhook usa el límite `throttle:120,1`; para cargas de producción revisar el comportamiento de reintentos y los límites recomendados.

## Comprobaciones previas a la entrega

- Confirmar `git status` limpio.
- Confirmar que `.env` y credenciales nunca se añadan al repositorio.
- Ejecutar `docker compose config -q` (NO compartir la salida completa de `docker compose config`, ya que puede contener contraseñas).
- Ejecutar pruebas, migraciones y `php artisan l5-swagger:generate`.
- Verificar Swagger UI en `http://127.0.0.1:8091/api/documentation`.
- Probar GET de productos y GET `/up` desde navegador.
- Actualizar README con qué está probado de verdad.
