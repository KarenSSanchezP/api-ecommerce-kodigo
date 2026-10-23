<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('api')?->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso exclusivo para administradores.',
            ], 403);
        }
        return $next($request);
    }
}
