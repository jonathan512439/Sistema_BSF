<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware para verificar rol de usuario y estado activo
 * 
 * Uso en rutas:
 * - Route::middleware(['auth:web', 'role:superadmin'])
 * - Route::middleware(['auth:web', 'role:archivist'])
 * - Route::middleware(['auth:web', 'role:reader'])
 * - Route::middleware(['auth:web', 'role:archivist,reader']) // Múltiples roles
 */
class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            // Para API/AJAX, retornar JSON 401
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'UNAUTHENTICATED',
                    'message' => 'No autenticado. Por favor inicie sesión.',
                ], 401);
            }
            
            return redirect()->route('login')
                ->with('error', 'Debe iniciar sesión para acceder a este recurso.');
        }

        $user = Auth::user();

        // Verificar que el usuario esté activo
        if ($user->status !== \App\Models\User::STATUS_ACTIVE) {
            // Si está invitado, redirigir a cambio de contraseña
            if ($user->status === \App\Models\User::STATUS_INVITED) {
                return redirect()->route('invitation.show', ['token' => $user->invitation_token])
                    ->with('warning', 'Debe establecer su contraseña antes de continuar.');
            }

            // Si está disabled, cerrar sesión y rechazar
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Su cuenta ha sido desactivada. Contacte al administrador.');
        }

        // Verificar que el usuario tenga uno de los roles permitidos
        if (!empty($roles) && !in_array($user->role, $roles, true)) {
            // Si es una petición AJAX/API, retornar JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'FORBIDDEN',
                    'message' => 'No tiene permisos para realizar esta acción.',
                ], 403);
            }

            // Si es petición web, retornar a dashboard con error
            return redirect()->route('dashboard')
                ->with('error', 'No tiene permisos para acceder a este recurso.');
        }

        return $next($request);
    }
}
