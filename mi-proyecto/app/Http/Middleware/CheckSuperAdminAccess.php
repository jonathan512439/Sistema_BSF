<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para restringir acceso de SuperAdmin
 * 
 * SuperAdmin SOLO puede acceder a gestión de usuarios (/api/admin/users)
 * NO puede acceder a: dashboard, documentos, auditoría, reportes, etc.
 */
class CheckSuperAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Si el usuario es SuperAdmin, bloqueamos acceso a APIs del sistema
        // EXCEPTO /api/admin/* (gestión de usuarios) y endpoints básicos
        if ($user->role === 'superadmin') {
            $path = $request->path();
            
            // Permitir acceso a rutas de admin y autenticación básica
            $allowed = [
                'api/admin',
                'api/me',
                'api/catalogs',  // NECESARIO para cargar la app
                'api/health',
                'api/wm-context',
                'logout',
                'login',
                '/',
                'app',
                'dashboard',
            ];
            
            // Verificar si la ruta comienza con alguna de las permitidas
            foreach ($allowed as $allowedPath) {
                if (str_starts_with($path, $allowedPath) || $path === $allowedPath) {
                    return $next($request);
                }
            }
            
            // Si la ruta es API de documentos, auditoría, reportes, etc.
            // Bloquear acceso
            $blocked = [
                'api/documentos',
                'api/docs',
                'api/audit',
                'api/reports',
                'stream/',
            ];
            
            foreach ($blocked as $blockedPath) {
                if (str_starts_with($path, $blockedPath)) {
                    abort(403, 'Los SuperAdmin solo tienen acceso a gestión de usuarios.');
                }
            }
        }
        
        return $next($request);
    }
}
