<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordChangeRequest;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\User;
use App\Models\UserAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    // Cambio de contraseña forzado / propio (para usuario autenticado)
    public function showForceChangeForm()
    {
        return view('auth.password_force');
    }

    public function updateForced(PasswordChangeRequest $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->password             = Hash::make($request->password);
        $user->must_change_password = false;

        // Si venía de invitado y está activando por primera vez, se asume activo
        if ($user->isInvited()) {
            $user->status               = User::STATUS_ACTIVE;
            $user->invitation_token     = null;
            $user->invitation_expires_at= null;
        }

        $user->save();

        UserAction::log(
            $user->id,
            $user->id,
            'password_changed_self',
            'Usuario cambió su propia contraseña (forzada o voluntaria).',
            $request
        );

        return redirect()->route('dashboard')->with('status', 'Contraseña actualizada correctamente.');
    }

    // Aceptar invitación (no autenticado)
    public function showInvitationForm(Request $request, string $token)
    {
        $user = User::where('invitation_token', $token)->first();

        if (! $user || $user->isDisabled()) {
            abort(404);
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            return view('auth.invitation_expired');
        }

        return view('auth.invitation_accept', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function acceptInvitation(AcceptInvitationRequest $request)
    {
        $user = User::where('invitation_token', $request->token)->first();

        if (! $user || $user->isDisabled()) {
            abort(404);
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            return view('auth.invitation_expired');
        }

        $user->password              = Hash::make($request->password);
        $user->status                = User::STATUS_ACTIVE;
        $user->must_change_password  = false;
        $user->password_changed_at   = now();   // ✅ nuevo
        $user->invitation_token      = null;
        $user->invitation_expires_at = null;
        $user->save();


        UserAction::log(
            $user->id,
            $user->id,
            'invitation_accepted',
            'Usuario activó su cuenta desde invitación y definió contraseña.',
            $request
        );

        return redirect()->route('spa')->with('status', 'Contrasena actualizada correctamente.');
    }
}
