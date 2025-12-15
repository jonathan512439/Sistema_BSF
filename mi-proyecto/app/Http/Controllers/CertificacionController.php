<?php

namespace App\Http\Controllers;

use App\Models\Certificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertificacionController extends Controller
{
    /**
     * Guardar una nueva certificación
     * POST /api/certificaciones
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'documento_id' => 'required|exists:documentos,id',
                'numero_certificacion' => 'required|string|max:50',
                'texto_introduccion' => 'nullable|string',
                'nombre_personal' => 'required|string|max:255',
                'ci' => 'required|string|max:20',
                'lugar_expedicion' => 'required|string|max:100',
                'fecha_ingreso' => 'required|string',
                'designacion' => 'required|string',
                'ultimo_destino' => 'required|string',
                'fecha_emision' => 'required|date',
                'elaborado_por' => 'required|string|max:255',
                'cargo_elaborador' => 'required|string|max:255',
                'nombre_comandante' => 'required|string|max:255',
                'cargo_comandante' => 'required|string|max:255',
            ]);

            $certificacion = Certificacion::create([
                ...$validated,
                'usuario_id' => auth()->id()
            ]);

            Log::info('[CERTIFICACION] Guardada exitosamente', [
                'id' => $certificacion->id,
                'documento_id' => $certificacion->documento_id,
                'usuario_id' => $certificacion->usuario_id
            ]);

            return response()->json([
                'ok' => true,
                'certificacion' => $certificacion
            ]);

        } catch (\Throwable $e) {
            Log::error('[CERTIFICACION] Error al guardar', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'CERTIFICACION_SAVE_FAIL',
                'message' => 'Error al guardar certificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una certificación existente
     * PUT /api/certificaciones/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $certificacion = Certificacion::findOrFail($id);

            $validated = $request->validate([
                'numero_certificacion' => 'required|string|max:50',
                'texto_introduccion' => 'nullable|string',
                'nombre_personal' => 'required|string|max:255',
                'ci' => 'required|string|max:20',
                'lugar_expedicion' => 'required|string|max:100',
                'fecha_ingreso' => 'required|string',
                'designacion' => 'required|string',
                'ultimo_destino' => 'required|string',
                'fecha_emision' => 'required|date',
                'elaborado_por' => 'required|string|max:255',
                'cargo_elaborador' => 'required|string|max:255',
                'nombre_comandante' => 'required|string|max:255',
                'cargo_comandante' => 'required|string|max:255',
            ]);

            $certificacion->update($validated);

            Log::info('[CERTIFICACION] Actualizada exitosamente', [
                'id' => $certificacion->id,
                'documento_id' => $certificacion->documento_id,
                'usuario_id' => $certificacion->usuario_id
            ]);

            return response()->json([
                'ok' => true,
                'certificacion' => $certificacion
            ]);

        } catch (\Throwable $e) {
            Log::error('[CERTIFICACION] Error al actualizar', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'CERTIFICACION_UPDATE_FAIL',
                'message' => 'Error al actualizar certificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar certificaciones de un documento
     * GET /api/documentos/{documento}/certificaciones
     */
    public function index($documentoId)
    {
        try {
            $certificaciones = Certificacion::where('documento_id', $documentoId)
                ->with('usuario:id,name,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'ok' => true,
                'certificaciones' => $certificaciones
            ]);

        } catch (\Throwable $e) {
            Log::error('[CERTIFICACION] Error al listar', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'CERTIFICACION_LIST_FAIL',
                'message' => 'Error al cargar certificaciones'
            ], 500);
        }
    }

    /**
     * Listar TODAS las certificaciones del sistema
     * GET /api/certificaciones/all
     */
    public function listAll()
    {
        try {
            $certificaciones = Certificacion::with([
                'usuario:id,name,email',
                'documento:id,titulo'
            ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'ok' => true,
                'certificaciones' => $certificaciones
            ]);

        } catch (\Throwable $e) {
            Log::error('[CERTIFICACION] Error al listar todas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'CERTIFICACION_LIST_ALL_FAIL',
                'message' => 'Error al cargar certificaciones'
            ], 500);
        }
    }
}
