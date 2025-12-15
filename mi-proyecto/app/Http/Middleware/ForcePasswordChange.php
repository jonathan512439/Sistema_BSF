<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Evitar bucle
        if ($request->routeIs('password.force.*')) {
            return $next($request);
        }

        if ($user->must_change_password ?? false) {
            return redirect()->route('password.force.show');
        }

        return $next($request);
    }
}
