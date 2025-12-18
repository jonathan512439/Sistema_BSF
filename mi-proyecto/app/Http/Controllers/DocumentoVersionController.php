<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\User;
use App\Services\DocumentVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentoVersionController extends Controller
{
    private DocumentVersionService $versionService;

    public function __construct(DocumentVersionService $versionService)
    {
        $this->versionService = $versionService;
    }

    /**
     * GET /api/documentos/{id}/versiones
     * Listar todas las versiones de un documento
     */
    public function index($documentoId)
    {
        try {
            $documento = Documento::findOrFail($documentoId);

            // Obtener versiones sin eager load problemático
            $versiones = DocumentoVersion::where('documento_id', $documento->id)
                ->with(['cambios'])
                ->orderBy('version_numero', 'desc')
                ->get();

            // Cargar usuarios manualmente para evitar N+1
            $userIds = $versiones->pluck('creado_por')->unique()->filter();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            // Agregar creado_por_name manualmente con foreach
            foreach ($versiones as $version) {
                $user = $users->get($version->creado_por);
                $version->creado_por_name = $user ? $user->name : 'Sistema';
            }

            return response()->json([
                'ok' => true,
                'versiones' => $versiones,
                'documento' => [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'version_actual' => $documento->version_actual,
                    'total_versiones' => $documento->total_versiones,
                    'estado' => $documento->estado,
                    'versionado_habilitado' => $documento->versionado_habilitado,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar versiones: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error al cargar versiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/documentos/{id}/versiones
     * Crear nueva versión manual con archivo
     */
    public function store($documentoId, Request $request)
    {
        try {
            $documento = Documento::findOrFail($documentoId);

            // Verificar permisos
            if (!$request->user() || !$request->user()->can('doc.version')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tiene permisos para crear versiones'
                ], 403);
            }

            // Validar request
            $validated = $request->validate([
                'archivo' => 'required|file|mimes:pdf|max:51200', // 50MB
                'motivo' => 'required|string|min:10|max:500'
            ]);

            // Crear versión
            $version = $this->versionService->crearVersionConArchivo(
                documento: $documento,
                archivo: $request->file('archivo'),
                motivo: $validated['motivo'],
                userId: $request->user()->id,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'ok' => true,
                'message' => 'Nueva versión creada exitosamente',
                'version' => $version,
                'documento' => [
                    'version_actual' => $documento->fresh()->version_actual,
                    'total_versiones' => $documento->fresh()->total_versiones,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al crear versión: ' . $e->getMessage(), [
                'documento_id' => $documentoId,
                'usuario' => $request->user()?->id
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * POST /api/documentos/{id}/versiones/{numero}/restaurar
     * Restaurar una versión anterior
     */
    public function restore($documentoId, $numero, Request $request)
    {
        try {
            $documento = Documento::findOrFail($documentoId);

            // Verificar permisos
            if (!$request->user() || !$request->user()->can('doc.version')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tiene permisos para restaurar versiones'
                ], 403);
            }

            // Validar
            $validated = $request->validate([
                'motivo' => 'required|string|min:10|max:500'
            ]);

            // Restaurar
            $this->versionService->restaurarVersion(
                documento: $documento,
                versionNumero: (int) $numero,
                motivo: $validated['motivo'],
                userId: $request->user()->id
            );

            return response()->json([
                'ok' => true,
                'message' => "Versión {$numero} restaurada exitosamente",
                'documento' => [
                    'version_actual' => $documento->fresh()->version_actual,
                    'total_versiones' => $documento->fresh()->total_versiones,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al restaurar versión: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * GET /api/versiones/{id}/descargar
     * Descargar una versión específica
     */
    public function download($versionId)
    {
        try {
            $version = DocumentoVersion::findOrFail($versionId);

            $filePath = storage_path('app/' . $version->archivo_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }

            $nombreDescarga = "v{$version->version_numero}_{$version->archivo_nombre}";

            return response()->download($filePath, $nombreDescarga);

        } catch (\Exception $e) {
            Log::error('Error al descargar versión: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error al descargar archivo'
            ], 500);
        }
    }

    /**
     * GET /api/versiones/{id}/ver
     * Ver PDF de una versión en el navegador
     */
    public function view($versionId)
    {
        try {
            $version = DocumentoVersion::findOrFail($versionId);

            $filePath = storage_path('app/' . $version->archivo_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="v' . $version->version_numero . '_' . $version->archivo_nombre . '"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al ver versión: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error al cargar archivo'
            ], 500);
        }
    }

    /**
     * POST /api/versiones/{id1}/comparar/{id2}
     * Comparar dos versiones
     */
    public function compare($id1, $id2)
    {
        try {
            $cambios = $this->versionService->compararVersiones((int) $id1, (int) $id2);

            $v1 = DocumentoVersion::findOrFail($id1);
            $v2 = DocumentoVersion::findOrFail($id2);

            return response()->json([
                'ok' => true,
                'version_1' => [
                    'numero' => $v1->version_numero,
                    'fecha' => $v1->creado_en,
                    'usuario' => $v1->creado_por_name,
                ],
                'version_2' => [
                    'numero' => $v2->version_numero,
                    'fecha' => $v2->creado_en,
                    'usuario' => $v2->creado_por_name,
                ],
                'cambios' => $cambios,
                'total_cambios' => count($cambios)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al comparar versiones: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error al comparar versiones'
            ], 500);
        }
    }
    public function agregarPaginas($documentoId, Request $request)
    {
        try {
            $validated = $request->validate(['imagenes' => 'required|array|min:1', 'imagenes.*' => 'required|image|mimes:jpeg,jpg,png', 'motivo' => 'required|string|min:10|max:500']);
            $version = $this->versionService->agregarPaginasDesdeImagenes(documentoId: $documentoId, imagenes: $request->file('imagenes'), motivo: $validated['motivo'], userId: $request->user()->id, ip: $request->ip(), userAgent: $request->userAgent());
            return response()->json(['ok' => true, 'message' => 'Versión creada', 'version' => $version]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
