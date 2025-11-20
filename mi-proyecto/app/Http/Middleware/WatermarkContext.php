<?php

namespace App\Http\Middleware;

use Closure;

class WatermarkContext
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        app()->instance('wm.user', $user?->name ?? 'guest');
        app()->instance('wm.email', $user?->email ?? '-');
        app()->instance('wm.ip', $request->ip());
        app()->instance('wm.ts', now()->toDateTimeString());
        return $next($request);
    }
}
