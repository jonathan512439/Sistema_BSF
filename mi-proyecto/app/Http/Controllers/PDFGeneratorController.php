<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneratePDFRequest;
use App\Services\PDFGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PDFGeneratorController extends Controller
{
    public function __construct(
        private PDFGeneratorService $pdfService
    ) {
    }

    /**
     * Genera un PDF a partir de imágenes subidas
     *
     * @param GeneratePDFRequest $request
     * @return BinaryFileResponse|JsonResponse
     */
    public function generate(GeneratePDFRequest $request)
    {
        try {
            // Limpiar archivos temporales antiguos
            $this->pdfService->cleanupOldTempFiles();

            // Obtener parámetros validados
            $images = $request->file('images');
            $rotations = $request->input('rotations', []);
            $paperSize = $request->input('paper_size', 'A4');
            $orientation = $request->input('orientation', 'portrait');
            $quality = $request->input('quality', 85);

            // Generar PDF
            $pdfPath = $this->pdfService->generateFromImages(
                images: $images,
                rotations: $rotations,
                paperSize: $paperSize,
                orientation: $orientation,
                quality: $quality
            );

            // Verificar que el archivo se generó correctamente
            if (!file_exists($pdfPath)) {
                throw new \Exception('El PDF no se generó correctamente');
            }

            $fileSize = filesize($pdfPath);

            Log::info('PDF generado desde imágenes', [
                'user_id' => auth()->id(),
                'num_images' => count($images),
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'file_size' => $fileSize,
            ]);

            // Retornar archivo y eliminarlo después de enviar
            return response()
                ->download($pdfPath, 'documento_generado.pdf', [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => $fileSize,
                ])
                ->deleteFileAfterSend(true);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'VALIDATION_ERROR',
                'messages' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error generando PDF desde imágenes', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'PDF_GENERATION_FAILED',
                'message' => 'Error al generar el PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Limpia archivos temporales antiguos manualmente
     */
    public function cleanup(): JsonResponse
    {
        try {
            $this->pdfService->cleanupOldTempFiles();

            return response()->json([
                'ok' => true,
                'message' => 'Archivos temporales limpiados correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'CLEANUP_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
