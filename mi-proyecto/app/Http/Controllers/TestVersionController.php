<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\DocumentoVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestVersionController extends Controller
{
    public function testVersions($documentoId)
    {
        try {
            Log::info("[TEST] Iniciando testVersions para documento {$documentoId}");

            // Prueba 1: Retornar JSON hardcodeado
            return response()->json([
                'ok' => true,
                'test' => 'working',
                'documento_id' => $documentoId,
                'versiones' => [
                    [
                        'id' => 1,
                        'version_numero' => 1,
                        'creado_por_name' => 'Test User'
                    ]
                ],
                'documento' => [
                    'id' => $documentoId,
                    'titulo' => 'Test Document',
                    'version_actual' => 1,
                    'total_versiones' => 1,
                    'estado' => 'capturado',
                    'versionado_habilitado' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[TEST] Error: " . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Test error: ' . $e->getMessage()
            ], 500);
        }
    }
}
