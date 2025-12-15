<?php

namespace App\Http\Controllers;

use App\Models\Seccion;
use App\Models\Subseccion;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Obtener todas las secciones con conteo de documentos
     * GET /api/categories/secciones
     */
    public function getSecciones(Request $request)
    {
        try {
            // RBAC: Determine if user is reader
            $isReader = $request->user() && $request->user()->isReader();

            $secciones = Seccion::withCount([
                'documentos' => function ($query) use ($isReader) {
                    $query->whereNull('deleted_at');
                    // Readers can't see confidential documents
                    if ($isReader) {
                        $query->where('is_confidential', false);
                    }
                }
            ])
                ->withCount([
                    'subsecciones' => function ($query) {
                        $query->whereNull('deleted_at');
                    }
                ])
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get()
                ->map(function ($seccion) {
                    return [
                        'id' => $seccion->id,
                        'nombre' => $seccion->nombre,
                        'descripcion' => $seccion->descripcion,
                        'documentos_count' => $seccion->documentos_count,
                        'subsecciones_count' => $seccion->subsecciones_count,
                    ];
                });

            return response()->json([
                'ok' => true,
                'data' => $secciones,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'ERROR_GET_SECCIONES',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener subsecciones de una sección con conteo
     * GET /api/categories/secciones/{seccionId}/subsecciones
     */
    public function getSubsecciones(Request $request, $seccionId)
    {
        try {
            // Verificar que la sección existe
            $seccion = Seccion::whereNull('deleted_at')->findOrFail($seccionId);

            // RBAC: Determine if user is reader
            $isReader = $request->user() && $request->user()->isReader();

            $subsecciones = Subseccion::where('seccion_id', $seccionId)
                ->withCount([
                    'documentos' => function ($query) use ($isReader) {
                        $query->whereNull('deleted_at');
                        // Readers can't see confidential documents
                        if ($isReader) {
                            $query->where('is_confidential', false);
                        }
                    }
                ])
                ->whereNull('deleted_at')
                ->orderBy('nombre')
                ->get()
                ->map(function ($subseccion) {
                    return [
                        'id' => $subseccion->id,
                        'nombre' => $subseccion->nombre,
                        'descripcion' => $subseccion->descripcion,
                        'seccion_id' => $subseccion->seccion_id,
                        'documentos_count' => $subseccion->documentos_count,
                    ];
                });

            return response()->json([
                'ok' => true,
                'seccion' => [
                    'id' => $seccion->id,
                    'nombre' => $seccion->nombre,
                ],
                'data' => $subsecciones,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'SECCION_NOT_FOUND',
                'message' => 'Sección no encontrada',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'ERROR_GET_SUBSECCIONES',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todos los tipos de documento con conteo
     * GET /api/categories/tipos-documento
     */
    public function getTiposDocumento(Request $request)
    {
        try {
            // RBAC: Determine if user is reader
            $isReader = $request->user() && $request->user()->isReader();

            $tipos = TipoDocumento::withCount([
                'documentos' => function ($query) use ($isReader) {
                    $query->whereNull('deleted_at');
                    // Readers can't see confidential documents
                    if ($isReader) {
                        $query->where('is_confidential', false);
                    }
                }
            ])
                ->whereNull('deleted_at')
                ->orderBy('categoria')
                ->orderBy('nombre')
                ->get()
                ->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'nombre' => $tipo->nombre,
                        'descripcion' => $tipo->descripcion,
                        'categoria' => $tipo->categoria ?? 'Sin categoría',
                        'documentos_count' => $tipo->documentos_count,
                    ];
                });

            // Agrupar por categoría
            $agrupados = $tipos->groupBy('categoria')->map(function ($items, $categoria) {
                return [
                    'categoria' => $categoria,
                    'tipos' => $items->values(),
                    'total_documentos' => $items->sum('documentos_count'),
                ];
            })->values();

            return response()->json([
                'ok' => true,
                'data' => $tipos,
                'agrupados' => $agrupados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'ERROR_GET_TIPOS',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas resumidas para el dashboard
     * GET /api/categories/stats
     */
    public function getStats(Request $request)
    {
        try {
            $total = DB::table('documentos')
                ->whereNull('deleted_at')
                ->count();

            $validados = DB::table('documentos')
                ->whereNull('deleted_at')
                ->where('estado', 'validado')
                ->count();

            $sellados = DB::table('documentos')
                ->whereNull('deleted_at')
                ->where('estado', 'sellado')
                ->count();

            $recientes = DB::table('documentos')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total_documentos' => $total,
                    'validados' => $validados,
                    'sellados' => $sellados,
                    'recientes_7_dias' => $recientes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'ERROR_GET_STATS',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
