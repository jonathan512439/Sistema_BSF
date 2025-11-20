<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class DemoActor
{
    public function handle($request, Closure $next)
    {
        // Lee X-Demo-User o query ?user_id= para la demo sin login
        $userId = $request->header('X-Demo-User') ?: $request->query('user_id');

        if ($userId) {
            $userId = (int) $userId;

            // Importante: usar explícitamente la conexión de negocio (bsf_core)
            try {
                $row = DB::connection('mysql')
                    ->table('users')
                    ->where('id', $userId)
                    ->first();
            } catch (\Throwable $e) {
                $row = null;
            }

            if (!$row) {
                // Fallback demo: si por alguna razón no existe en BD,
                // creamos un "usuario lógico" solo para el ciclo del request.
                // Así siempre hay un actor ligado al ID que viene desde el front.
                $row = (object) [
                    'id'    => $userId,
                    'name'  => 'Demo User '.$userId,
                    'email' => 'demo'.$userId.'@demo.local',
                ];
            }

            // Simula usuario autenticado solo en el ciclo del request
            $request->setUserResolver(function () use ($row) {
                return $row;
            });
        }

        return $next($request);
    }
}
