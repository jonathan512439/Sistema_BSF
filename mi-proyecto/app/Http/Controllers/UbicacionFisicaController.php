<?php

namespace App\Http\Controllers;

use App\Models\UbicacionFisica;
use App\Models\DocumentoUbicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UbicacionFisicaController extends Controller
{
    /**
     * GET /api/ubicaciones
     * Listar todas las ubicaciones físicas
     */
    public function index(Request $request)
    {
        $query = UbicacionFisica::with('padre', 'hijos');

        // Filtro: solo activas
        if ($request->boolean('activas')) {
            $query->activas();
        }

        // Filtro: solo raíces (sin padre)
        if ($request->boolean('raices')) {
            $query->whereNull('ubicacion_padre_id');
        }

        // Filtro por tipo
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Ordenar
        $query->orderBy('nombre');

        $ubicaciones = $query->get();

        return response()->json([
            'ubicaciones' => $ubicaciones,
        ]);
    }

    /**
     * GET /api/ubicaciones/tree
     * Obtener árbol jerárquico de ubicaciones
     */
    public function tree()
    {
        $raices = UbicacionFisica::activas()
            ->whereNull('ubicacion_padre_id')
            ->with('hijosRecursivos')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'tree' => $raices,
        ]);
    }

    /**
     * GET /api/ubicaciones/{id}
     * Ver detalles de una ubicación
     */
    public function show(int $id)
    {
        $ubicacion = UbicacionFisica::with(['padre', 'hijos', 'documentos'])
            ->find($id);

        if (!$ubicacion) {
            return response()->json([
                'error' => 'Ubicación no encontrada',
            ], 404);
        }

        // Calcular estadísticas
        $totalDocumentos = DocumentoUbicacion::where('ubicacion_fisica_id', $id)
            ->whereNull('fecha_retiro')
            ->count();

        $ocupacion = $ubicacion->capacidad_max > 0
            ? ($totalDocumentos / $ubicacion->capacidad_max) * 100
            : 0;

        return response()->json([
            'ubicacion' => $ubicacion,
            'stats' => [
                'total_documentos' => $totalDocumentos,
                'ocupacion_porcentaje' => round($ocupacion, 2),
                'disponible' => max(0, $ubicacion->capacidad_max - $totalDocumentos),
            ],
            'ruta' => $ubicacion->getRutaCompleta(),
        ]);
    }

    /**
     * POST /api/ubicaciones
     * Crear nueva ubicación
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:50|unique:ubicaciones_fisicas,codigo',
            'tipo' => 'required|in:deposito,almacen,estante,caja,carpeta',
            'ubicacion_padre_id' => 'nullable|exists:ubicaciones_fisicas,id',
            'descripcion' => 'nullable|string|max:500',
            'capacidad_max' => 'nullable|integer|min:0',
        ]);

        try {
            $ubicacion = UbicacionFisica::create([
                'nombre' => $request->input('nombre'),
                'codigo' => $request->input('codigo'),
                'tipo' => $request->input('tipo'),
                'ubicacion_padre_id' => $request->input('ubicacion_padre_id'),
                'descripcion' => $request->input('descripcion'),
                'capacidad_max' => $request->input('capacidad_max', 0),
                'activo' => true,
            ]);

            Log::info('Ubicación física creada', [
                'ubicacion_id' => $ubicacion->id,
                'nombre' => $ubicacion->nombre,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'ubicacion' => $ubicacion->load('padre'),
                'message' => 'Ubicación creada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear ubicación física', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al crear ubicación',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/ubicaciones/{id}
     * Actualizar ubicación
     */
    public function update(Request $request, int $id)
    {
        $ubicacion = UbicacionFisica::find($id);

        if (!$ubicacion) {
            return response()->json([
                'error' => 'Ubicación no encontrada',
            ], 404);
        }

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'codigo' => 'sometimes|required|string|max:50|unique:ubicaciones_fisicas,codigo,' . $id,
            'tipo' => 'sometimes|required|in:deposito,almacen,estante,caja,carpeta',
            'ubicacion_padre_id' => 'nullable|exists:ubicaciones_fisicas,id',
            'descripcion' => 'nullable|string|max:500',
            'capacidad_max' => 'nullable|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        try {
            // Prevenir ciclos en jerarquía
            if ($request->filled('ubicacion_padre_id')) {
                $padreId = $request->input('ubicacion_padre_id');

                if ($padreId == $id) {
                    return response()->json([
                        'error' => 'Una ubicación no puede ser su propio padre',
                    ], 400);
                }

                // Verificar que el nuevo padre no sea un hijo de esta ubicación
                $padre = UbicacionFisica::find($padreId);
                if ($padre && $padre->esHijoDe($id)) {
                    return response()->json([
                        'error' => 'No se puede crear un ciclo en la jerarquía',
                    ], 400);
                }
            }

            $ubicacion->update($request->only([
                'nombre',
                'codigo',
                'tipo',
                'ubicacion_padre_id',
                'descripcion',
                'capacidad_max',
                'activo',
            ]));

            Log::info('Ubicación física actualizada', [
                'ubicacion_id' => $ubicacion->id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'ubicacion' => $ubicacion->load('padre'),
                'message' => 'Ubicación actualizada exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar ubicación física', [
                'ubicacion_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al actualizar ubicación',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/ubicaciones/{id}
     * Desactivar ubicación (soft delete)
     */
    public function destroy(int $id)
    {
        $ubicacion = UbicacionFisica::find($id);

        if (!$ubicacion) {
            return response()->json([
                'error' => 'Ubicación no encontrada',
            ], 404);
        }

        // Verificar si tiene documentos activos
        $documentosActivos = DocumentoUbicacion::where('ubicacion_fisica_id', $id)
            ->whereNull('fecha_retiro')
            ->count();

        if ($documentosActivos > 0) {
            return response()->json([
                'error' => 'No se puede desactivar una ubicación con documentos activos',
                'documentos_activos' => $documentosActivos,
            ], 400);
        }

        try {
            $ubicacion->deleted_by = auth()->id();
            $ubicacion->delete();

            Log::info('Ubicación física desactivada', [
                'ubicacion_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación desactivada exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al desactivar ubicación física', [
                'ubicacion_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al desactivar ubicación',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/ubicaciones/{id}/documentos
     * Listar documentos en una ubicación
     */
    public function documentos(int $id)
    {
        $ubicacion = UbicacionFisica::find($id);

        if (!$ubicacion) {
            return response()->json([
                'error' => 'Ubicación no encontrada',
            ], 404);
        }

        $documentos = DocumentoUbicacion::with(['documento', 'responsable'])
            ->where('ubicacion_fisica_id', $id)
            ->whereNull('fecha_retiro')
            ->orderBy('fecha_asignacion', 'desc')
            ->get();

        return response()->json([
            'ubicacion' => $ubicacion,
            'documentos' => $documentos,
            'total' => $documentos->count(),
        ]);
    }

    /**
     * GET /api/ubicaciones/stats
     * Estadísticas generales de ubicaciones
     */
    public function stats()
    {
        $total = UbicacionFisica::count();
        $activas = UbicacionFisica::activas()->count();

        $porTipo = UbicacionFisica::activas()
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->get()
            ->pluck('total', 'tipo');

        $ocupacion = UbicacionFisica::activas()
            ->whereNotNull('capacidad_max')
            ->where('capacidad_max', '>', 0)
            ->get()
            ->map(function ($ubicacion) {
                $ocupados = DocumentoUbicacion::where('ubicacion_fisica_id', $ubicacion->id)
                    ->whereNull('fecha_retiro')
                    ->count();

                return [
                    'id' => $ubicacion->id,
                    'nombre' => $ubicacion->nombre,
                    'capacidad' => $ubicacion->capacidad_max,
                    'ocupados' => $ocupados,
                    'porcentaje' => ($ocupados / $ubicacion->capacidad_max) * 100,
                ];
            })
            ->sortByDesc('porcentaje')
            ->take(10);

        return response()->json([
            'total_ubicaciones' => $total,
            'activas' => $activas,
            'inactivas' => $total - $activas,
            'por_tipo' => $porTipo,
            'mas_ocupadas' => $ocupacion->values(),
        ]);
    }
}
