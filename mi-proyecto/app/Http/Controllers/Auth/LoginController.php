<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UserLogin;
use App\Models\UserAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        $maxAttempts = 5;
        $lockMinutes = 15;

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        // Estado del usuario
        if ($user) {
            if ($user->isDisabled()) {
                UserAction::log(
                    $user->id,
                    $user->id,
                    'login_denied_disabled',
                    'Intento de login con usuario deshabilitado.',
                    $request
                );

                return back()->withErrors([
                    'email' => 'Tu cuenta está deshabilitada. Contacta al administrador.',
                ])->onlyInput('email');
            }

            if ($user->isInvited()) {
                UserAction::log(
                    $user->id,
                    $user->id,
                    'login_denied_invited',
                    'Intento de login con usuario invitado que aún no activó la cuenta.',
                    $request
                );

                return back()->withErrors([
                    'email' => 'Debes activar tu cuenta desde el enlace de invitación.',
                ])->onlyInput('email');
            }

            if ($user->locked_until instanceof Carbon && $user->locked_until->isFuture()) {
                $minutes = $user->locked_until->diffInMinutes(now()) + 1;

                UserAction::log(
                    $user->id,
                    $user->id,
                    'login_denied_locked',
                    'Intento de login mientras la cuenta está bloqueada.',
                    $request
                );

                return back()->withErrors([
                    'email' => "Tu cuenta está bloqueada temporalmente. Inténtalo nuevamente en aproximadamente {$minutes} minuto(s).",
                ])->onlyInput('email');
            }
        }

        // Intentar autenticación
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            /** @var User $authUser */
            $authUser = Auth::user();

            // Reset de bloqueo
            $authUser->failed_logins = 0;
            $authUser->locked_until  = null;
            $authUser->save();

            // Registrar login
            UserLogin::create([
                'user_id'      => $authUser->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 500),
                'logged_in_at' => now(),
            ]);

            UserAction::log(
                $authUser->id,
                $authUser->id,
                'login_success',
                'Inicio de sesión correcto.',
                $request
            );

            // Si debe cambiar contraseña, el middleware lo redirigirá
            return redirect()->intended(route('dashboard'));
        }

        // Fallo de credenciales
        if ($user) {
            $user->failed_logins = $user->failed_logins + 1;

            if ($user->failed_logins >= $maxAttempts) {
                $user->locked_until   = now()->addMinutes($lockMinutes);
                $user->failed_logins  = 0;

                UserAction::log(
                    $user->id,
                    $user->id,
                    'user_locked',
                    "Usuario bloqueado tras {$maxAttempts} intentos fallidos.",
                    $request
                );
            }

            $user->save();

            UserAction::log(
                $user->id,
                $user->id,
                'login_failed',
                'Intento de login con credenciales incorrectas.',
                $request
            );
        } else {
            // Usuario inexistente: log genérico sin user_id
            UserAction::log(
                null,
                null,
                'login_failed_unknown_user',
                'Intento de login con correo inexistente.',
                $request
            );
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            UserAction::log(
                $user->id,
                $user->id,
                'logout',
                'Cierre de sesión.',
                $request
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
