<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Servicio para gestionar invitaciones de usuarios
 * 
 * Responsabilidades:
 * - Generar tokens seguros de invitación
 * - Enviar emails de invitación
 * - Validar tokens
 * - Activar usuarios tras cambio de contraseña
 */
class UserInvitationService
{
    /**
     * Duración de tokens de invitación en días (configurable en .env)
     */
    const INVITATION_EXPIRATION_DAYS = 7;

    /**
     * Generar token de invitación y enviar email
     *
     * @param User $user Usuario que recibirá la invitación
     * @return bool True si el email se envió correctamente
     */
    public function sendInvitation(User $user): bool
    {
        // Generar token seguro único
        $token = $this->generateToken();

        // Calcular fecha de expiración
        $expiresAt = Carbon::now()->addDays(
            config('app.invitation_expiration_days', self::INVITATION_EXPIRATION_DAYS)
        );

        // Actualizar usuario con token y expiración
        $user->invitation_token = $token;
        $user->invitation_expires_at = $expiresAt;
        $user->status = User::STATUS_INVITED;
        $user->must_change_password = true;
        $user->save();

        // URL de invitación
        $invitationUrl = route('invitation.show', ['token' => $token]);

        try {
            // Enviar email con template
            Mail::send('emails.user-invitation', [
                'user' => $user,
                'invitationUrl' => $invitationUrl,
                'expiresAt' => $expiresAt,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Invitación al Sistema BSF');
            });

            Log::info('[USER_INVITATION] Email enviado correctamente', [
                'user_id' => $user->id,
                'email' => $user->email,
                'expires_at' => $expiresAt->toDateTimeString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[USER_INVITATION] Error al enviar email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validar token de invitación
     *
     * @param string $token Token a validar
     * @return User|null Usuario si el token es válido, null en caso contrario
     */
    public function validateToken(string $token): ?User
    {
        $user = User::where('invitation_token', $token)
            ->where('status', User::STATUS_INVITED)
            ->first();

        if (!$user) {
            Log::warning('[USER_INVITATION] Token no encontrado o usuario no está en estado invited', [
                'token' => substr($token, 0, 10) . '...',
            ]);
            return null;
        }

        // Verificar que el token no haya expirado
        if ($user->invitation_expires_at && Carbon::now()->isAfter($user->invitation_expires_at)) {
            Log::warning('[USER_INVITATION] Token expirado', [
                'user_id' => $user->id,
                'expired_at' => $user->invitation_expires_at->toDateTimeString(),
            ]);
            return null;
        }

        return $user;
    }

    /**
     * Activar usuario tras cambio exitoso de contraseña
     *
     * @param User $user Usuario a activar
     * @param string $newPassword Nueva contraseña (ya hasheada)
     * @return void
     */
    public function activateUser(User $user, string $hashedPassword): void
    {
        $user->password = $hashedPassword;
        $user->status = User::STATUS_ACTIVE;
        $user->must_change_password = false;
        $user->invitation_token = null; // Limpiar token usado
        $user->invitation_expires_at = null;
        $user->save();

        Log::info('[USER_INVITATION] Usuario activado correctamente', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ]);
    }

    /**
     * Generar token seguro único
     *
     * @return string Token de 64 caracteres
     */
    private function generateToken(): string
    {
        // Generar token hasta que sea único
        do {
            $token = Str::random(64);
        } while (User::where('invitation_token', $token)->exists());

        return $token;
    }

    /**
     * Reenviar invitación a un usuario
     *
     * @param User $user Usuario que recibirá nueva invitación
     * @return bool True si se envió correctamente
     */
    public function resendInvitation(User $user): bool
    {
        // Solo se puede reenviar si está en estado invited
        if ($user->status !== User::STATUS_INVITED) {
            Log::warning('[USER_INVITATION] Intento de reenvío a usuario no invited', [
                'user_id' => $user->id,
                'status' => $user->status,
            ]);
            return false;
        }

        return $this->sendInvitation($user);
    }
}
