<?php

namespace App\Http\Controllers;

use App\Services\BlockchainAnchorService;
use App\Models\LedgerAnchor;
use App\Models\AnchoringConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnchorController extends Controller
{
    protected $anchorService;

    public function __construct(BlockchainAnchorService $anchorService)
    {
        $this->anchorService = $anchorService;
    }

    /**
     * GET /api/anchors
     * Listar todas las anclas con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = LedgerAnchor::query();

        // Filtro: solo firmadas
        if ($request->boolean('firmadas')) {
            $query->firmadas();
        }

        // Filtro: solo sin firmar
        if ($request->boolean('pendientes_firma')) {
            $query->pendientesFirma();
        }

        // Filtro: solo publicadas
        if ($request->boolean('publicadas')) {
            $query->publicadas();
        }

        // Ordenar
        $query->orderBy('id', 'desc');

        // Paginar
        $perPage = $request->input('per_page', 20);
        $anclas = $query->paginate($perPage);

        return response()->json([
            'anclas' => $anclas->items(),
            'pagination' => [
                'total' => $anclas->total(),
                'per_page' => $anclas->perPage(),
                'current_page' => $anclas->currentPage(),
                'last_page' => $anclas->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/anchors/{id}
     * Ver detalles de un ancla específica
     */
    public function show(int $id)
    {
        $ancla = LedgerAnchor::find($id);

        if (!$ancla) {
            return response()->json([
                'error' => 'Ancla no encontrada',
            ], 404);
        }

        // Verificar firma si existe
        $firmaValida = null;
        if ($ancla->estaFirmada()) {
            $firmaValida = $this->anchorService->verifyAnchorSignature($id);
        }

        return response()->json([
            'ancla' => $ancla,
            'firma_valida' => $firmaValida,
            'cantidad_registros' => $ancla->cantidad_registros,
            'rango' => $ancla->rango,
        ]);
    }

    /**
     * POST /api/anchors/verify
     * Verificar integridad de toda la cadena de anclas
     */
    public function verify()
    {
        try {
            $result = $this->anchorService->verifyAnchorChain();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error al verificar cadena de anclas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al verificar cadena de anclas',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/anchors/create
     * Crear ancla manual (solo admin)
     */
    public function create(Request $request)
    {
        $request->validate([
            'from_id' => 'nullable|integer|min:1',
            'to_id' => 'nullable|integer|min:1',
        ]);

        try {
            // Si no se especifica rango, obtener siguiente automático
            if (!$request->has('from_id') || !$request->has('to_id')) {
                $range = $this->anchorService->getNextAnchorRange();

                if (!$range) {
                    return response()->json([
                        'error' => 'No hay suficientes registros para crear ancla',
                    ], 400);
                }

                $fromId = $range['from_id'];
                $toId = $range['to_id'];
            } else {
                $fromId = $request->input('from_id');
                $toId = $request->input('to_id');
            }

            // Crear ancla
            $result = $this->anchorService->createAnchor($fromId, $toId);

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['error'],
                ], 400);
            }

            // Firmar si está habilitado
            $sign = $request->boolean(
                'sign',
                AnchoringConfig::getBoolean('signing_enabled', false)
            );

            if ($sign) {
                $this->anchorService->signAnchor($result['ancla_id']);
            }

            return response()->json([
                'success' => true,
                'ancla_id' => $result['ancla_id'],
                'hash_raiz' => $result['hash_raiz'],
                'firmada' => $sign,
                'ancla' => $result['ancla'],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear ancla manual', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al crear ancla',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/anchors/config
     * Obtener configuración actual
     */
    public function getConfig()
    {
        return response()->json([
            'config' => AnchoringConfig::getAll(),
        ]);
    }

    /**
     * PUT /api/anchors/config
     * Actualizar configuración (solo admin)
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required',
        ]);

        try {
            $userId = auth()->id();

            $success = AnchoringConfig::set(
                $request->input('key'),
                $request->input('value'),
                $userId
            );

            if (!$success) {
                return response()->json([
                    'error' => 'Clave de configuración no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar configuración',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/anchors/stats
     * Estadísticas del sistema de anclaje
     */
    public function stats()
    {
        $total = LedgerAnchor::count();
        $firmadas = LedgerAnchor::firmadas()->count();
        $publicadas = LedgerAnchor::publicadas()->count();
        $pendientes = LedgerAnchor::pendientesFirma()->count();

        $config = AnchoringConfig::getAll();
        $nextRange = $this->anchorService->getNextAnchorRange();

        return response()->json([
            'total_anclas' => $total,
            'firmadas' => $firmadas,
            'publicadas' => $publicadas,
            'pendientes_firma' => $pendientes,
            'auto_enabled' => $config['auto_anchor_enabled'] ?? false,
            'block_size' => $config['anchor_block_size'] ?? 1000,
            'last_anchor_id' => $config['last_anchor_id'] ?? 0,
            'next_range' => $nextRange,
        ]);
    }
}
