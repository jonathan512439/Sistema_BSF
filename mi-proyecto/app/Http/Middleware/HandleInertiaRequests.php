<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class HandleInertiaRequests
{
    // ...

    public function share(Request $request): array
    {
        $demoUser = $request->session()->get('demo_user');

        return [
            'user' => $demoUser,
            'can'  => $demoUser['can'] ?? [],
            // si luego reactivas auth real, aquí puedes fusionar permisos
            // 'user' => fn() => $request->user(),
            // 'can'  => fn() => [...],
        ];
    }
}
