<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class AccessLog
{
    // app/Http/Middleware/AccessLog.php

public function handle($request, Closure $next)
{
    $response = $next($request);

    try {
        $docId = $request->route('documento') ?? $request->get('documento_id');
        if ($docId instanceof Model) {
            $docId = $docId->getKey();
        }

        $accion = $request->route('accion') ?? $request->get('accion');

        // motivo_id (query o body)
        $motivoId = $request->get('motivo_id');
        if ($motivoId !== null && $motivoId !== '') {
            $motivoId = (int) $motivoId;
        } else {
            $motivoId = null;
        }

        if ($docId && in_array($accion, ['view', 'download', 'print'], true)) {

            $user = $request->user();
            $userId = $user->id ?? null;

            if (!$userId) {
                $fromHeader = $request->header('X-Demo-User');
                $fromQuery  = $request->query('user_id');
                $userId = $fromHeader ?: $fromQuery;
                if ($userId !== null) {
                    $userId = (int) $userId;
                }
            }

            $ip = $request->ip() ?? '127.0.0.1';
            $ipBin = @inet_pton($ip);

            DB::connection('mysql')->table('accesos_documento')->insert([
                'documento_id' => (int) $docId,
                'user_id'      => $userId,
                'accion'       => $accion,
                // ✅ ahora sí guardamos motivo_id
                'motivo_id'    => $motivoId,
                'ip'           => $ipBin,
                'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                'created_at'   => now(),
            ]);
        }
    } catch (\Throwable $e) {
        // No romper la respuesta por errores de logging
    }

    return $response;
}

}
