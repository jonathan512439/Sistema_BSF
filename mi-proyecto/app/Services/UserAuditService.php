<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Servicio para auditoría de gestión de usuarios
 * 
 * Registra TODAS las acciones realizadas por superadmins
 * en la tabla user_management_audit
 */
class UserAuditService
{
    /**
     * Registrar creación de usuario
     *
     * @param User $admin Superadmin que creó el usuario
     * @param User $newUser Usuario creado
     * @param array $inputData Datos del formulario de creación
     * @return void
     */
    public function logUserCreation(User $admin, User $newUser, array $inputData = []): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $newUser->id,
            'target_username' => $newUser->username,
            'action' => 'create',
            'old_values' => null,
            'new_values' => [
                'name' => $newUser->name,
                'email' => $newUser->email,
                'username' => $newUser->username,
                'role' => $newUser->role,
                'status' => $newUser->status,
            ],
            'reason' => 'Usuario creado por ' . $admin->name,
        ]);
    }

    /**
     * Registrar cambio de rol
     *
     * @param User $admin Superadmin que realizó el cambio
     * @param User $user Usuario afectado
     * @param string $oldRole Rol anterior
     * @param string $newRole Rol nuevo
     * @param string|null $reason Motivo del cambio
     * @return void
     */
    public function logRoleChange(User $admin, User $user, string $oldRole, string $newRole, ?string $reason = null): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'action' => 'update_role',
            'old_values' => ['role' => $oldRole],
            'new_values' => ['role' => $newRole],
            'reason' => $reason ?? "Cambio de rol de {$oldRole} a {$newRole}",
        ]);
    }

    /**
     * Registrar cambio de estado
     *
     * @param User $admin Superadmin que realizó el cambio
     * @param User $user Usuario afectado
     * @param string $oldStatus Estado anterior
     * @param string $newStatus Estado nuevo
     * @param string|null $reason Motivo del cambio
     * @return void
     */
    public function logStatusChange(User $admin, User $user, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'action' => 'update_status',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
            'reason' => $reason ?? "Cambio de estado de {$oldStatus} a {$newStatus}",
        ]);
    }

    /**
     * Registrar reutilización de username
     *
     * @param User $admin Superadmin que creó el usuario con username duplicado
     * @param User $newUser Nuevo usuario con username reutilizado
     * @param string $oldUsername Username que se está reutilizando
     * @param User|null $oldUser Usuario anterior que usaba ese username (si aún existe)
     * @return void
     */
    public function logUsernameReuse(User $admin, User $newUser, string $oldUsername, ?User $oldUser = null): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $newUser->id,
            'target_username' => $newUser->username,
            'action' => 'reuse_username',
            'old_values' => [
                'previous_user_id' => $oldUser?->id,
                'username_was_used_by' => $oldUser?->name ?? 'Usuario eliminado',
            ],
            'new_values' => [
                'new_user_id' => $newUser->id,
                'new_user_name' => $newUser->name,
                'username' => $oldUsername,
            ],
            'reason' => "Username '{$oldUsername}' reutilizado para nuevo usuario {$newUser->name}",
        ]);

        Log::warning('[USER_AUDIT] Username reutilizado', [
            'admin_id' => $admin->id,
            'new_user_id' => $newUser->id,
            'username' => $oldUsername,
        ]);
    }

    /**
     * Registrar dar de baja (soft delete)
     *
     * @param User $admin Superadmin que dio de baja
     * @param User $user Usuario dado de baja
     * @param string $reason Motivo de la baja  (REQUERIDO)
     * @return void
     */
    public function logDeletion(User $admin, User $user, string $reason): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'action' => 'delete',
            'old_values' => [
                'status' => $user->status,
                'deleted_at' => null,
            ],
            'new_values' => [
                'status' => User::STATUS_DISABLED,
                'deleted_at' => Carbon::now()->toDateTimeString(),
                'deleted_by' => $admin->id,
            ],
            'reason' => $reason,
        ]);
    }

    /**
     * Registrar dar de alta (restore)
     *
     * @param User $admin Superadmin que dio de alta
     * @param User $user Usuario restaurado
     * @return void
     */
    public function logRestoration(User $admin, User $user): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'action' => 'restore',
            'old_values' => [
                'status' => User::STATUS_DISABLED,
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
            ],
            'new_values' => [
                'status' => User::STATUS_ACTIVE,
                'deleted_at' => null,
            ],
            'reason' => 'Usuario restaurado por ' . $admin->name,
        ]);
    }

    /**
     * Registrar reenvío de invitación
     *
     * @param User $admin Superadmin que reenvió
     * @param User $user Usuario que recibe nueva invitación
     * @return void
     */
    public function logResendInvitation(User $admin, User $user): void
    {
        $this->log([
            'admin_user_id' => $admin->id,
            'admin_username' => $admin->username,
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'action' => 'resend_invitation',
            'old_values' => null,
            'new_values' => [
                'email' => $user->email,
                'invitation_expires_at' => $user->invitation_expires_at?->toDateTimeString(),
            ],
            'reason' => 'Invitación reenviada a ' . $user->email,
        ]);
    }

    /**
     * Registrar entrada en la tabla de auditoría
     *
     * @param array $data Datos del log
     * @return void
     */
    private function log(array $data): void
    {
        try {
            // Obtener IP y user agent del request actual
            $request = request();
            $data['ip_address'] = $request?->ip();
            $data['user_agent'] = $request?->userAgent();

            // Convertir arrays a JSON
            if (isset($data['old_values']) && is_array($data['old_values'])) {
                $data['old_values'] = json_encode($data['old_values']);
            }
            if (isset($data['new_values']) && is_array($data['new_values'])) {
                $data['new_values'] = json_encode($data['new_values']);
            }

            // Insertar en BD
            DB::table('user_management_audit')->insert($data);

            Log::info('[USER_AUDIT] Registro guardado', [
                'action' => $data['action'],
                'admin_id' => $data['admin_user_id'],
                'target_id' => $data['target_user_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Si falla el log de auditoría, registrar error pero no interrumpir flujo
            Log::error('[USER_AUDIT] Error al guardar registro de auditoría', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Obtener historial de auditoría de un usuario
     *
     * @param int $userId ID del usuario
     * @param int $limit Cantidad de registros a obtener
     * @return \Illuminate\Support\Collection
     */
    public function getUserAuditHistory(int $userId, int $limit = 50)
    {
        return DB::table('user_management_audit')
            ->where('target_user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener todas las acciones realizadas por un admin
     *
     * @param int $adminId ID del admin
     * @param int $limit Cantidad de registros a obtener
     * @return \Illuminate\Support\Collection
     */
    public function getAdminActions(int $adminId, int $limit = 100)
    {
        return DB::table('user_management_audit')
            ->where('admin_user_id', $adminId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
