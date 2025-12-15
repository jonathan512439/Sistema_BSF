<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OcrClient
{
    protected string $baseUrl;
    protected string $defaultLang;

    public function __construct()
    {
        $this->baseUrl     = rtrim(config('services.ocr.base_url') ?? env('OCR_BASE_URL', 'http://127.0.0.1:8001'), '/');
        $this->defaultLang = env('OCR_DEFAULT_LANG', 'spa');
    }

    /**
     * Normaliza la ruta del archivo para que sea compatible con Python.
     * Convierte barras invertidas de Windows a barras normales.
     *
     * @param string $path Ruta absoluta del archivo
     * @return string Ruta normalizada
     */
    private function normalizePath(string $path): string
    {
        // Convertir barras invertidas a barras normales
        $normalized = str_replace('\\', '/', $path);
        
        // Asegurar que la ruta existe
        if (!file_exists($path)) {
            \Log::warning("[OCR_CLIENT] Archivo no existe: {$path}");
        }
        
        \Log::debug("[OCR_CLIENT] Ruta normalizada: {$path} -> {$normalized}");
        
        return $normalized;
    }

    /**
     * Llama al microservicio OCR enviando la ruta física del PDF.
     *
     * @param string      $absolutePath Ruta absoluta en el filesystem del servidor
     * @param int|null    $documentoId  Solo para logging (opcional)
     * @return array      Respuesta JSON decodificada del servicio OCR
     */
    public function procesarDocumento(string $absolutePath, ?int $documentoId = null): array
    {
        // Validar que el archivo existe antes de procesar
        if (!file_exists($absolutePath)) {
            \Log::error("[OCR_CLIENT] Archivo no existe: {$absolutePath}");
            throw new \RuntimeException("El archivo PDF no existe: {$absolutePath}");
        }

        \Log::info("[OCR_CLIENT] Iniciando OCR para documento {$documentoId}");
        \Log::debug("[OCR_CLIENT] Ruta original: {$absolutePath}");

        $url = $this->baseUrl . '/api/ocr/documento';

        // Normalizar la ruta para Python
        $normalizedPath = $this->normalizePath($absolutePath);

        $payload = [
            'pdf_path'     => $normalizedPath,
            'idioma'       => $this->defaultLang,
            'documento_id' => $documentoId,
        ];

        \Log::debug("[OCR_CLIENT] Payload: " . json_encode($payload));

        $response = Http::timeout(300)->post($url, $payload);

        \Log::debug("[OCR_CLIENT] Respuesta HTTP {$response->status()}");

        if (!$response->ok()) {
            $body = $response->body();
            $errorMsg = 'Error en OCR service: HTTP ' . $response->status() . ' ' . $body;
            \Log::error("[OCR_CLIENT] {$errorMsg}");
            throw new \RuntimeException($errorMsg);
        }

        $data = $response->json();

        if (!is_array($data) || !($data['ok'] ?? false)) {
            $errorMsg = 'Servicio OCR devolvió ok=false o respuesta inválida';
            \Log::error("[OCR_CLIENT] {$errorMsg}. Respuesta: " . json_encode($data));
            throw new \RuntimeException($errorMsg);
        }

        \Log::info("[OCR_CLIENT] OCR completado exitosamente para documento {$documentoId}");
        \Log::debug("[OCR_CLIENT] Confidence: " . ($data['confidence_media'] ?? 'N/A'));

        return $data;
    }
}
