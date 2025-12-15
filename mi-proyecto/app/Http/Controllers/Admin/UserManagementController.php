<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\UserInvitationService;
use App\Services\UserAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller para gestión de usuarios (solo accesible por superadmins)
 */
class UserManagementController extends Controller
{
    private UserInvitationService $invitationService;
    private UserAuditService $auditService;

    public function __construct(
        UserInvitationService $invitationService,
        UserAuditService $auditService
    ) {
        $this->invitationService = $invitationService;
        $this->auditService = $auditService;
        // Middleware de autenticación y roles se maneja en routes/web.php
    }

    /**
     * Listar todos los usuarios (incluidos disabled)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = User::withTrashed(); // Incluir soft-deleted

            // Filtros opcionales
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'email', 'username', 'role', 'status', 
                       'created_at', 'deleted_at', 'invitation_expires_at']);

            return response()->json([
                'ok' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('[USER_MGMT] Error al listar usuarios', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_LISTING_USERS',
                'message' => 'Error al cargar la lista de usuarios.',
            ], 500);
        }
    }

    /**
     * Crear nuevo usuario
     *
     * @param CreateUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateUserRequest $request)
    {
        try {
            DB::beginTransaction();

            $admin = $request->user();

            // Verificar si username ya existe (permitido pero auditado)
            $existingUser = User::withTrashed()
                ->where('username', $request->username)
                ->first();

            $usernameReused = (bool) $existingUser;

            // Crear usuario
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->username = $request->username;
            $user->role = $request->role;
            $user->status = User::STATUS_INVITED; // Siempre empieza como invited
            $user->must_change_password = true;
            
            // Password temporal (será reemplazada al aceptar invitación)
            $user->password = bcrypt(Str::random(32));
            
            $user->save();

            // Auditar creación
            $this->auditService->logUserCreation($admin, $user, $request->validated());

            // Si se reutilizó username, auditar
            if ($usernameReused) {
                $this->auditService->logUsernameReuse(
                    $admin,
                    $user,
                    $request->username,
                    $existingUser
                );
            }

            // Enviar invitación por email
            $emailSent = $this->invitationService->sendInvitation($user);

            DB::commit();

            Log::info('[USER_MGMT] Usuario creado exitosamente', [
                'admin_id' => $admin->id,
                'new_user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'username_reused' => $usernameReused,
                'email_sent' => $emailSent,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Usuario creado correctamente. Se ha enviado una invitación por email.',
                'user' => $user,
                'username_reused' => $usernameReused,
                'email_sent' => $emailSent,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[USER_MGMT] Error al crear usuario', [
                'error' => $e->getMessage(),
                'admin_id' => $request->user()->id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_CREATING_USER',
                'message' => 'Error al crear el usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar detalles de un usuario
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        try {
            $user = User::withTrashed()->findOrFail($id);

            // Obtener historial de auditoría del usuario
            $auditHistory = $this->auditService->getUserAuditHistory($id, 20);

            return response()->json([
                'ok' => true,
                'user' => $user,
                'audit_history' => $auditHistory,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'USER_NOT_FOUND',
                'message' => 'Usuario no encontrado.',
            ], 404);
        }
    }

    /**
     * Actualizar usuario
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        try {
            DB::beginTransaction();

            $admin = $request->user();
            $user = User::withTrashed()->findOrFail($id);

            // No permitir editar superadmins
            if ($user->role === User::ROLE_SUPERADMIN) {
                return response()->json([
                    'ok' => false,
                    'error' => 'FORBIDDEN',
                    'message' => 'No se puede editar un usuario superadmin.',
                ], 403);
            }

            // Guardar valores anteriores para auditoría
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ];

            // Actualizar campos
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            // Auditar cambio de rol
            if ($request->has('role') && $request->role !== $user->role) {
                $this->auditService->logRoleChange(
                    $admin,
                    $user,
                    $oldValues['role'],
                    $request->role,
                    $request->reason
                );
                $user->role = $request->role;
            }

            // Auditar cambio de estado
            if ($request->has('status') && $request->status !== $user->status) {
                $this->auditService->logStatusChange(
                    $admin,
                    $user,
                    $oldValues['status'],
                    $request->status,
                    $request->reason
                );
                $user->status = $request->status;
            }

            $user->save();

            DB::commit();

            Log::info('[USER_MGMT] Usuario actualizado', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'changes' => $request->validated(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Usuario actualizado correctamente.',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[USER_MGMT] Error al actualizar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_UPDATING_USER',
                'message' => 'Error al actualizar el usuario.',
            ], 500);
        }
    }

    /**
     * Dar de baja usuario (soft delete)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Debe proporcionar un motivo para dar de baja al usuario.',
        ]);

        try {
            DB::beginTransaction();

            $admin = $request->user();
            $user = User::findOrFail($id);

            // No permitir eliminar superadmins
            if ($user->role === User::ROLE_SUPERADMIN) {
                return response()->json([
                    'ok' => false,
                    'error' => 'FORBIDDEN',
                    'message' => 'No se puede dar de baja a un usuario superadmin.',
                ], 403);
            }

            // Cambiar estado a disabled
            $user->status = User::STATUS_DISABLED;
            $user->deleted_by = $admin->id;
            $user->delete_reason = $request->reason;
            $user->save();

            // Soft delete
            $user->delete();

            // Auditar
            $this->auditService->logDeletion($admin, $user, $request->reason);

            DB::commit();

            Log::info('[USER_MGMT] Usuario dado de baja', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Usuario dado de baja correctamente.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[USER_MGMT] Error al dar de baja usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_DELETING_USER',
                'message' => 'Error al dar de baja el usuario.',
            ], 500);
        }
    }

    /**
     * Dar de alta usuario (restore)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, int $id)
    {
        try {
            DB::beginTransaction();

            $admin = $request->user();
            $user = User::withTrashed()->findOrFail($id);

            if (!$user->trashed()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'USER_NOT_DELETED',
                    'message' => 'El usuario no está dado de baja.',
                ], 400);
            }

            // Restaurar
            $user->restore();
            $user->status = User::STATUS_ACTIVE;
            $user->deleted_by = null;
            $user->delete_reason = null;
            $user->save();

            // Auditar
            $this->auditService->logRestoration($admin, $user);

            DB::commit();

            Log::info('[USER_MGMT] Usuario restaurado', [
                'admin_id' => $admin->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Usuario dado de alta correctamente.',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[USER_MGMT] Error al restaurar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_RESTORING_USER',
                'message' => 'Error al dar de alta el usuario.',
            ], 500);
        }
    }

    /**
     * Reenviar invitación
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendInvitation(Request $request, int $id)
    {
        try {
            $admin = $request->user();
            $user = User::findOrFail($id);

            if ($user->status !== User::STATUS_INVITED) {
                return response()->json([
                    'ok' => false,
                    'error' => 'INVALID_STATUS',
                    'message' => 'Solo se puede reenviar invitación a usuarios en estado "invited".',
                ], 400);
            }

            $emailSent = $this->invitationService->resendInvitation($user);

            // Auditar
            $this->auditService->logResendInvitation($admin, $user);

            if ($emailSent) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Invitación reenviada correctamente.',
                ]);
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'EMAIL_FAILED',
                    'message' => 'No se pudo enviar el email. Revise los logs.',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('[USER_MGMT] Error al reenviar invitación', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'ERROR_RESENDING_INVITATION',
                'message' => 'Error al reenviar la invitación.',
            ], 500);
        }
    }
}