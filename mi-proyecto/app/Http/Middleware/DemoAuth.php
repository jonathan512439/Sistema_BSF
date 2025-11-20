<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DemoAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('demo_user')) {
            return redirect()->route('login')
                ->with('status', 'Inicie sesión para continuar (demo).');
        }

        return $next($request);
    }
}
        