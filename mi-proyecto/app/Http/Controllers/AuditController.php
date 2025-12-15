<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditController extends Controller
{
    /**
     * Get audit logs with filtering and pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 50);
            $page = $request->input('page', 1);

            // Build query - using 'audit' connection to bsf_audit database
            $query = DB::connection('audit')
                ->table('ledger as l')
                ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
                ->select([
                    'l.id',
                    'l.evento',
                    'l.actor_id',
                    'u.name as actor_name',
                    'l.objeto_tipo',
                    'l.objeto_id',
                    'l.payload_json as payload',
                    'l.ip',
                    'l.user_agent',
                    'l.hash_prev',
                    'l.hash_self',
                    'l.created_at'
                ])
                ->orderBy('l.id', 'desc');

            // Apply filters
            if ($request->filled('start_date')) {
                $query->where('l.created_at', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->where('l.created_at', '<=', $request->input('end_date') . '23:59:59');
            }

            if ($request->filled('action')) {
                $query->where('l.evento', $request->input('action'));
            }

            if ($request->filled('user_id')) {
                $query->where('l.actor_id', $request->input('user_id'));
            }

            if ($request->filled('documento_id')) {
                $query->where('l.objeto_tipo', 'documento')
                    ->where('l.objeto_id', $request->input('documento_id'));
            }

            // Search in event name or actor name
            if ($request->filled('search')) {
                $search = '%' . $request->input('search') . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('l.evento', 'like', $search)
                        ->orWhere('u.name', 'like', $search)
                        ->orWhere('l.objeto_tipo', 'like', $search);
                });
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $logs = $query->offset($offset)->limit($perPage)->get();

            // Decode JSON payload for each log
            foreach ($logs as $log) {
                if ($log->payload) {
                    $log->payload = json_decode($log->payload, true);
                }
                // Convert IP to readable format if exists
                if ($log->ip) {
                    $log->ip = inet_ntop($log->ip);
                }
            }

            return response()->json([
                'logs' => $logs,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]);

        } catch (\Throwable $e) {
            Log::error('AUDIT_INDEX_ERROR: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'AUDIT_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single audit log details
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $log = DB::connection('audit')
                ->table('ledger as l')
                ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
                ->select([
                    'l.id',
                    'l.evento',
                    'l.actor_id',
                    'u.name as actor_name',
                    'u.email as actor_email',
                    'l.objeto_tipo',
                    'l.objeto_id',
                    'l.payload_json as payload',
                    'l.ip',
                    'l.user_agent',
                    'l.hash_prev',
                    'l.hash_self',
                    'l.created_at'
                ])
                ->where('l.id', $id)
                ->first();

            if (!$log) {
                return response()->json([
                    'ok' => false,
                    'error' => 'NOT_FOUND',
                    'message' => 'Registro de auditoría no encontrado'
                ], 404);
            }

            // Decode payload
            if ($log->payload) {
                $log->payload = json_decode($log->payload, true);
            }

            // Convert IP
            if ($log->ip) {
                $log->ip = inet_ntop($log->ip);
            }

            return response()->json($log);

        } catch (\Throwable $e) {
            Log::error('AUDIT_SHOW_ERROR: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'AUDIT_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export audit logs to CSV
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        try {
            $query = DB::connection('audit')
                ->table('ledger as l')
                ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
                ->select([
                    'l.id',
                    'l.evento',
                    'u.name as actor_name',
                    'l.objeto_tipo',
                    'l.objeto_id',
                    'l.ip',
                    'l.created_at'
                ])
                ->orderBy('l.id', 'desc');

            // Apply same filters as index
            if ($request->filled('start_date')) {
                $query->where('l.created_at', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->where('l.created_at', '<=', $request->input('end_date') . ' 23:59:59');
            }

            if ($request->filled('action')) {
                $query->where('l.evento', $request->input('action'));
            }

            $logs = $query->limit(10000)->get(); // Limit to prevent memory issues

            $filename = 'auditoria_' . date('Y-m-d_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($logs) {
                $file = fopen('php://output', 'w');

                // Headers
                fputcsv($file, ['ID', 'Fecha', 'Usuario', 'Acción', 'Objeto Tipo', 'Objeto ID', 'IP']);

                // Data
                foreach ($logs as $log) {
                    $ip = $log->ip ? inet_ntop($log->ip) : '';
                    fputcsv($file, [
                        $log->id,
                        $log->created_at,
                        $log->actor_name ?: 'Sistema',
                        $log->evento,
                        $log->objeto_tipo,
                        $log->objeto_id,
                        $ip
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Throwable $e) {
            Log::error('AUDIT_EXPORT_ERROR: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => 'EXPORT_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Legacy method - Get ledger for specific document
     */
    public function ledger($documentoId)
    {
        $rows = DB::connection('audit')
            ->table('ledger')
            ->where('objeto_tipo', 'documento')
            ->where('objeto_id', (int) $documentoId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($rows);
    }

    /**
     * Legacy method - Get access log for document
     */
    public function accessLog($documentoId)
    {
        $rows = DB::connection('mysql')
            ->table('accesos_documento')
            ->select('id', 'user_id', 'accion', 'motivo_id', 'created_at')
            ->where('documento_id', (int) $documentoId)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($rows);
    }

    /**
     * Endpoint mejorado que combina audit ledger + accesos a documentos
     * GET /api/audit/comprehensive
     */
    public function comprehensive(Request $request)
    {
        try {
            // Parámetros de filtrado
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $actionType = $request->input('action_type');
            $motivoId = $request->input('motivo_id');
            $search = $request->input('search');

            // ============================================
            // 1. OBTENER EVENTOS DEL LEDGER DE AUDITORÍA
            // ============================================
            $ledgerQuery = DB::connection('audit')
                ->table('ledger as l')
                ->leftJoin('bsf_core.users as u', 'u.id', '=', 'l.actor_id')
                ->select([
                    DB::raw('CONCAT("ledger_", l.id) as id'), // ID único
                    'l.created_at',
                    'l.evento',
                    'u.name as actor_name',
                    'l.objeto_tipo',
                    'l.objeto_id',
                    'l.ip',
                    'l.user_agent',
                    'l.payload_json as payload', // CORREGIDO: La columna es payload_json
                    DB::raw('NULL as motivo'),
                    DB::raw('NULL as doc_titulo'),
                    DB::raw('"ledger" as source')
                ])
                ->orderBy('l.created_at', 'desc')
                ->limit(250);

            // Aplicar filtros a ledger
            if ($startDate) {
                $ledgerQuery->where('l.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $ledgerQuery->where('l.created_at', '<=', $endDate . ' 23:59:59');
            }

            $ledgerLogs = $ledgerQuery->get()->toArray();

            // ============================================
            // 2. OBTENER ACCESOS A DOCUMENTOS
            // ============================================
            $accessQuery = DB::connection('mysql')->table('accesos_documento as ad')
                ->join('users as u', 'ad.user_id', '=', 'u.id')
                ->leftJoin('motivos_acceso as m', 'ad.motivo_id', '=', 'm.id')
                ->leftJoin('documentos as d', 'ad.documento_id', '=', 'd.id')
                ->select([
                    DB::raw('CONCAT("access_", ad.id) as id'), // ID único
                    'ad.created_at',
                    DB::raw('CONCAT("documento.", ad.accion) as evento'),
                    'u.name as actor_name',
                    DB::raw('"documento" as objeto_tipo'),
                    'ad.documento_id as objeto_id',
                    'ad.ip',
                    'ad.user_agent',
                    DB::raw('NULL as payload'), // NULL para consistencia con ledger
                    'm.descripcion as motivo',
                    'd.titulo as doc_titulo',
                    DB::raw('"access" as source')
                ])
                ->orderBy('ad.created_at', 'desc')
                ->limit(250);

            // Aplicar filtros a accesos
            if ($startDate) {
                $accessQuery->where('ad.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $accessQuery->where('ad.created_at', '<=', $endDate . ' 23:59:59');
            }
            if ($actionType && in_array($actionType, ['view', 'print', 'download'])) {
                $accessQuery->where('ad.accion', $actionType);
            }
            if ($motivoId) {
                $accessQuery->where('ad.motivo_id', $motivoId);
            }
            if ($search) {
                $search = '%' . $search . '%';
                $accessQuery->where(function ($q) use ($search) {
                    $q->where('u.name', 'like', $search)
                        ->orWhere('d.titulo', 'like', $search)
                        ->orWhere('m.descripcion', 'like', $search);
                });
            }

            $accessLogs = $accessQuery->get()->toArray();

            // ============================================
            // 3. COMBINAR Y ORDENAR EN PHP
            // ============================================
            $combined = array_merge($ledgerLogs, $accessLogs);

            // Ordenar por fecha descendente
            usort($combined, function ($a, $b) {
                return strcmp($b->created_at, $a->created_at);
            });

            // Limitar a 500 registros totales
            $combined = array_slice($combined, 0, 500);

            // Convertir IPs binarias y decodificar payloads JSON
            foreach ($combined as $log) {
                // Convertir IP binaria a legible
                if ($log->ip && (strlen($log->ip) === 4 || strlen($log->ip) === 16)) {
                    $log->ip = @inet_ntop($log->ip) ?: $log->ip;
                }

                // Decodificar payload JSON si existe
                if ($log->payload && is_string($log->payload)) {
                    try {
                        $log->payload = json_decode($log->payload, false);
                    } catch (\Throwable $e) {
                        // Si falla decodificación, dejar como está
                    }
                }
            }

            return response()->json([
                'ok' => true,
                'logs' => $combined,
                'total' => count($combined)
            ]);

        } catch (\Throwable $e) {
            Log::error('[AUDIT_COMPREHENSIVE] Error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'AUDIT_FETCH_FAIL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas de auditoría
     * GET /api/audit/stats
     */
    public function stats(Request $request)
    {
        try {
            $days = $request->input('days', 30); // Últimos 30 días por defecto
            $startDate = now()->subDays($days);

            // Total de accesos
            $totalAccesos = DB::connection('mysql')
                ->table('accesos_documento')
                ->where('created_at', '>=', $startDate)
                ->count();

            // Documentos más vistos
            $topDocs = DB::connection('mysql')
                ->table('accesos_documento as ad')
                ->join('documentos as d', 'ad.documento_id', '=', 'd.id')
                ->select([
                    'd.id',
                    'd.titulo',
                    DB::raw('COUNT(*) as views')
                ])
                ->where('ad.created_at', '>=', $startDate)
                ->groupBy('d.id', 'd.titulo')
                ->orderByDesc('views')
                ->limit(10)
                ->get();

            // Usuarios más activos
            $topUsers = DB::connection('mysql')
                ->table('accesos_documento as ad')
                ->join('users as u', 'ad.user_id', '=', 'u.id')
                ->select([
                    'u.id',
                    'u.name',
                    DB::raw('COUNT(*) as actions')
                ])
                ->where('ad.created_at', '>=', $startDate)
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('actions')
                ->limit(10)
                ->get();

            // Distribución por tipo de acción
            $actionDistribution = DB::connection('mysql')
                ->table('accesos_documento')
                ->select([
                    'accion',
                    DB::raw('COUNT(*) as count')
                ])
                ->where('created_at', '>=', $startDate)
                ->groupBy('accion')
                ->get();

            // Motivos más usados
            $topMotivos = DB::connection('mysql')
                ->table('accesos_documento as ad')
                ->join('motivos_acceso as m', 'ad.motivo_id', '=', 'm.id')
                ->select([
                    'm.id',
                    'm.descripcion',
                    DB::raw('COUNT(*) as uses')
                ])
                ->where('ad.created_at', '>=', $startDate)
                ->groupBy('m.id', 'm.descripcion')
                ->orderByDesc('uses')
                ->limit(5)
                ->get();

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total_accesos' => $totalAccesos,
                    'top_documentos' => $topDocs,
                    'top_usuarios' => $topUsers,
                    'action_distribution' => $actionDistribution,
                    'top_motivos' => $topMotivos
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('[AUDIT_STATS] Error al obtener estadísticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'STATS_FAIL',
                'message' => 'Error al cargar estadísticas'
            ], 500);
        }
    }

    /**
     * Método de compatibilidad - redirige a comprehensive
     * GET /api/audit/logs
     */
    public function logs(Request $request)
    {
        return $this->comprehensive($request);
    }
}
