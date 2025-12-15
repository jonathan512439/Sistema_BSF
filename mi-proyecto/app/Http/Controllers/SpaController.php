<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class SpaController extends Controller
{
    public function index()
    {
        // Devuelve el shell del SPA Vue
        return view('app');
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // Seguridad extra: si la ruta pierde el middleware auth, devolvemos 401 en vez de un fatal
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role,
            'status' => $user->status,
        ]);
    }
}
