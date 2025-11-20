<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DemoCanManageDocs
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('demo_user');
        $can  = $user['can'] ?? [];

        if (empty($can['docs.manage'])) {
            abort(403, 'No tiene permisos para gestionar documentos (demo).');
        }

        return $next($request);
    }
}
