<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ForcedPasswordController extends Controller
{
    public function showForm(Request $request)
    {
        return view('auth.force-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();

        $user->password             = Hash::make($request->password);
        $user->must_change_password = false;
        $user->password_changed_at  = now();
        $user->save();

        // Aquí podrías llamar a UserAction::log(...) si quieres auditar también este cambio.

        return redirect()->route('dashboard')
            ->with('status', 'Contraseña actualizada correctamente.');
    }
}