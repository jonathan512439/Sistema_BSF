<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RbacController extends Controller
{
    public function users()
    {
        try {
            $users = DB::table('users')->select('id','name','email')->get();

            // Si la tabla/existencia de catálogos RBAC falla, devolvemos arrays vacíos sin 500
            $userRoles = DB::table('user_role')
                ->join('roles','roles.id','=','user_role.role_id')
                ->select('user_role.user_id','roles.id as role_id','roles.name as role_name','roles.slug')
                ->get();

            $rolePerms = DB::table('role_permission')
                ->join('permissions','permissions.id','=','role_permission.permission_id')
                ->join('roles','roles.id','=','role_permission.role_id')
                ->select(
                    'roles.id as role_id',
                    'roles.name as role_name',
                    'permissions.slug as perm_slug',
                    'permissions.name as perm_name'
                )->get();

            return response()->json([
                'ok' => true,
                'users' => $users,
                'user_roles' => $userRoles,
                'role_permissions' => $rolePerms,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RBAC_LIST_FAIL: '.$e->getMessage());
            // Fallback seguro para demo: no rompemos la UI
            return response()->json([
                'ok' => false,
                'users' => [],
                'user_roles' => [],
                'role_permissions' => [],
                'message' => 'RBAC no disponible: '.$e->getMessage(),
            ], 200); // 200 para que el front se recupere con fallback
        }
    }
}
