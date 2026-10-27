# Ejemplos HTTP con PowerShell

Estas llamadas están pensadas para un entorno local recién instalado. **No publiques JWT ni contraseñas en capturas.**

```powershell
$base = 'http://127.0.0.1:8091/api/v1'

$register = @{
    name = 'Cliente de demostracion'
    email = 'cliente.demo@example.test'
    password = 'ClaveDemoSegura123!'
    password_confirmation = 'ClaveDemoSegura123!'
} | ConvertTo-Json

Invoke-RestMethod -Uri "$base/auth/register" -Method Post -ContentType 'application/json' -Body $register

$login = @{email='cliente.demo@example.test';password='ClaveDemoSegura123!'} | ConvertTo-Json
$authResponse = Invoke-RestMethod -Uri "$base/auth/login" -Method Post -ContentType 'application/json' -Body $login
$token = $authResponse.data.access_token
$headers = @{Authorization = "Bearer $token"}

Invoke-RestMethod -Uri "$base/auth/me" -Headers $headers
Invoke-RestMethod -Uri "$base/products"

# Primero consulta /products para verificar el ID real del producto.
$orderInput = @{items = @(@{product_id = 1; quantity = 1})} | ConvertTo-Json -Depth 6
$order = Invoke-RestMethod -Uri "$base/orders" -Method Post -Headers $headers -ContentType 'application/json' -Body $orderInput
$orderId = $order.data.id

Invoke-RestMethod -Uri "$base/orders/$orderId" -Headers $headers

# Sin STRIPE_SECRET_KEY valida, Checkout debe devolver HTTP 503.
# Si no vas a probar Stripe ahora, cancela la orden para reponer existencias:
Invoke-RestMethod -Uri "$base/orders/$orderId/cancel" -Method Post -Headers $headers
```

Al registrar por segunda vez el mismo correo recibirás error de validación por duplicado. Para las operaciones de administrador, registra un usuario independiente y cambia su rol manualmente SOLO en tu base de desarrollo, mediante una sesión local de Tinker autorizada:

```powershell
docker compose exec app php artisan tinker
```

```php
$user = App\Models\User::where('email', 'TU_ADMIN_LOCAL@example.test')->firstOrFail();
$user->forceFill(['is_admin' => true])->save();
```

No implementes un endpoint público de asignación de administradores y no publiques usuarios con contraseñas conocidas como seeders de ejemplo.
