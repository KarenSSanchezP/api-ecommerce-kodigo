<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return response()->json([
            'success' => true,
            'message' => 'Cliente registrado correctamente.',
            'data' => $user->only(['id', 'name', 'email']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $guard = auth('api');

        /** @var JWTGuard $guard */
        $token = $guard->attempt($request->validated());
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $request->user('api')]);
    }

    public function logout(): JsonResponse
    {
        $guard = auth('api');

        /** @var JWTGuard $guard */
        $guard->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada.'
        ]);
    }
}
