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

            // Obtener versiones de forma simple
            $versiones = DocumentoVersion::where('documento_id', $documento->id)
                ->orderBy('version_numero', 'desc')
                ->get();

            // Agregar creado_por_name a cada versión
            foreach ($versiones as $version) {
                if ($version->creado_por) {
                    $user = User::find($version->creado_por);
                    $version->creado_por_name = $user ? $user->name : 'Sistema';
                } else {
                    $version->creado_por_name = 'Sistema';
                }
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
            Log::error('[VERSION_INDEX] Error al listar versiones: ' . $e->getMessage());
            Log::error('[VERSION_INDEX] Trace: ' . $e->getTraceAsString());
            return response()->json([
                'ok' => false,
                'message' => 'Error al cargar versiones: ' . $e->getMessage()
            ], 500);
        }
    }

    // ... resto del código
}
