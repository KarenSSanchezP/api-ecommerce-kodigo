# Prueba de integración con Stripe (test mode)

## ¿Qué se ha comprobado?

Los tests de PHP usan `StripeCheckoutGateway` simulado para garantizar que el código prepara la sesión con los precios de la orden. El webhook se prueba con **eventos firmados localmente** que verifican la firma, los importes, los estados e idempotencia. **Esto no equivale a una transacción real contra Stripe.**

## Prueba real opcional sin dinero real

Solo si tienes acceso legítimo a una cuenta de Stripe en modo de prueba (por ejemplo una proporcionada por el docente):

1. Configura `STRIPE_SECRET_KEY` con una clave **`sk_test_...`** en el `.env` privado. No utilices claves live ni datos reales de tarjetas.
2. Instala/autentica Stripe CLI siguiendo la documentación oficial de Stripe.
3. Mantén Docker en ejecución (`docker compose up -d`).
4. En otra terminal ejecuta:

   ```powershell
   stripe listen --forward-to http://127.0.0.1:8091/api/v1/webhooks/stripe
   ```

5. Copia el secreto **`whsec_...`** que la CLI muestra en `STRIPE_WEBHOOK_SECRET` de `.env` y ejecuta:

   ```powershell
   docker compose exec app php artisan config:clear
   ```

6. Registra/loguea un cliente, crea una orden con inventario disponible y solicita `POST /api/v1/orders/{order}/checkout` con su JWT (ejemplos en [EJEMPLOS_API.md](EJEMPLOS_API.md)).
7. Abre la `checkout_url` real devuelta por Stripe y utiliza **solo datos de tarjeta de prueba publicados oficialmente por Stripe**. Observa la entrega del evento por la CLI.
8. Consulta la orden y el pago en phpMyAdmin. `orders.status` y `payments.status` deben pasar de `pending` / `checkout_pending` a `paid` después de un webhook firmado y válido.
9. Confirma que reenviar el mismo evento no duplica cambios. Para escenarios expirados, usa herramientas de test oficiales; evita modificar manualmente tablas mientras evalúas la idempotencia.

`stripe trigger checkout.session.completed` puede generar un evento de prueba **no asociado a ninguna sesión real guardada en tu base de datos**; no esperes que ese evento por sí solo marque una orden como pagada.

## Importante para la entrega

Si no dispones de credenciales Stripe test legítimas, describe el estado como: «Integración desarrollada con SDK oficial y webhooks firmados; pruebas automatizadas satisfactorias. Flujo Stripe en vivo (test mode) no ejecutado por falta de acceso a una cuenta de prueba».

No inventes capturas de pagos, credenciales ni resultados de Stripe.
