<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \Illuminate\Http\Middleware\HandleCors::class,
        \Illuminate\Http\Middleware\TrustProxies::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            // Cifrar cookies y validar CSRF para no dejar el frontend sin proteccion
            \Illuminate\Cookie\Middleware\EncryptCookies::class, // usamos el middleware base porque no existe uno en App\
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, // idem: clase base disponible
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // RBAC: Soporte para autenticación web (sesiones)
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Las rutas en api.php usan middleware(['auth:web']) explícitamente
            \App\Http\Middleware\WatermarkContext::class,
            'throttle:api',
        ],
    ];

    // Alias de middleware (Laravel 10/11 usa esta propiedad; evita "Target class [...] does not exist")
    protected $middlewareAliases = [
        'wm'        => \App\Http\Middleware\WatermarkContext::class,
        'accesslog' => \App\Http\Middleware\AccessLog::class,
        // Alias de auth activo para no dejar rutas sin proteger
        'auth'      => \App\Http\Middleware\Authenticate::class,
        'throttle'  => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'  => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'demo'      => \App\Http\Middleware\DemoActor::class,
        'demo.auth' => \App\Http\Middleware\DemoAuth::class,
        'force.password' => \App\Http\Middleware\ForcePasswordChange::class,
        // RBAC: Middleware de roles
        'role'      => \App\Http\Middleware\CheckUserRole::class,
    ];

    // Laravel <= 9 usa esta propiedad (compatibilidad)
    protected $routeMiddleware = [
        'wm'        => \App\Http\Middleware\WatermarkContext::class,
        'accesslog' => \App\Http\Middleware\AccessLog::class,
        'auth'      => \App\Http\Middleware\Authenticate::class,
        'throttle'  => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'  => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'demo'      => \App\Http\Middleware\DemoActor::class,
        'demo.auth' => \App\Http\Middleware\DemoAuth::class,
        'force.password' => \App\Http\Middleware\ForcePasswordChange::class,
        // RBAC: Middleware de roles
        'role'      => \App\Http\Middleware\CheckUserRole::class,
    ];
}
