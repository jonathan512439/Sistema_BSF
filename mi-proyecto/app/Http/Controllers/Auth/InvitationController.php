<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\UserInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

/**
 * Controller para manejar invitaciones y cambio de contraseña inicial
 */
class InvitationController extends Controller
{
    private UserInvitationService $invitationService;

    public function __construct(UserInvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Mostrar formulario de cambio de contraseña
     *
     * @param string $token Token de invitación
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(string $token)
    {
        // Validar token
        $user = $this->invitationService->validateToken($token);

        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'El enlace de invitación es inválido o ha expirado. Contacte al administrador.');
        }

        // Retornar vista con datos del usuario
        return view('auth.invitation-password', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Procesar cambio de contraseña
     *
     * @param string $token Token de invitación
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function process(string $token, Request $request)
    {
        // Validar token
        $user = $this->invitationService->validateToken($token);

        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'El enlace de invitación es inválido o ha expirado.');
        }

        // Validar contraseña
        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // Al menos una minúscula
                'regex:/[A-Z]/',      // Al menos una mayúscula
                'regex:/[0-9]/',      // Al menos un número
                'regex:/[@$!%*#?&]/', // Al menos un símbolo
            ],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un símbolo (@$!%*#?&).',
        ]);

        // Hashear contraseña y activar usuario
        $hashedPassword = Hash::make($validated['password']);
        $this->invitationService->activateUser($user, $hashedPassword);

        Log::info('[INVITATION] Usuario activado exitosamente', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ]);

        // Auto-login
        Auth::login($user);

        // Redirigir según rol
        $redirectRoute = match ($user->role) {
            User::ROLE_SUPERADMIN => 'admin.users.index',
            User::ROLE_ARCHIVIST => 'dashboard',
            User::ROLE_READER => 'dashboard',
            default => 'dashboard',
        };

        return redirect()->route($redirectRoute)
            ->with('success', '¡Bienvenido! Tu cuenta ha sido activada correctamente.');
    }
}
