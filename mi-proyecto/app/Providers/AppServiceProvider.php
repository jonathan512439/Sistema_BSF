<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::aliasMiddleware('force.password', ForcePasswordChange::class);

        // Registrar Observer para Documentos (Auditoría y Legal Holds)
        \App\Models\Documento::observe(\App\Observers\DocumentoObserver::class);
    }
}
