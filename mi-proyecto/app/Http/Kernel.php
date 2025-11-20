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
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // DemoActor antes de wm para que wm use el usuario resuelto
            \App\Http\Middleware\DemoActor::class,
            \App\Http\Middleware\WatermarkContext::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'wm'        => \App\Http\Middleware\WatermarkContext::class,
        'accesslog' => \App\Http\Middleware\AccessLog::class,
        // 'auth'    => \App\Http\Middleware\Authenticate::class,
        'throttle'  => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified'  => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'demo'      => \App\Http\Middleware\DemoActor::class,
        'demo.auth' => \App\Http\Middleware\DemoAuth::class,
        'demo.docs.manage' => \App\Http\Middleware\DemoCanManageDocs::class,
    ];
}
