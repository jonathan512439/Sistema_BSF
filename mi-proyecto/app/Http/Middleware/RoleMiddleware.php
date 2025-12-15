<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Acceso no autorizado.');
        }

        if (!in_array($user->role, $roles, true)) {
            abort(403, 'No tiene permisos para acceder a este recurso.');
        }

        return $next($request);
    }
}
