<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_customer_not_admin(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente',
            'email' => 'cliente@example.test',
            'password' => 'ClaveDemoSegura123!',
            'password_confirmation' => 'ClaveDemoSegura123!',
            'is_admin' => true,
        ])->assertCreated()->assertJsonPath('data.email', 'cliente@example.test');

        $user = User::where('email', 'cliente@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertNotSame('ClaveDemoSegura123!', $user->password);
    }

    public function test_login_issues_a_valid_jwt_and_allows_me(): void
    {
        User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.test',
            'password' => 'ClaveDemoSegura123!',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.test',
            'password' => 'ClaveDemoSegura123!',
        ])->assertOk()->json('data.access_token');

        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'cliente@example.test');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.test',
            'password' => 'ClaveDemoSegura123!',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.test',
            'password' => 'incorrecta',
        ])->assertUnauthorized();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
