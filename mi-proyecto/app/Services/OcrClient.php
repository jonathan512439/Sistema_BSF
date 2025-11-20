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
     * Llama al microservicio OCR enviando la ruta física del PDF.
     *
     * @param string      $absolutePath Ruta absoluta en el filesystem del servidor
     * @param int|null    $documentoId  Solo para logging (opcional)
     * @return array      Respuesta JSON decodificada del servicio OCR
     */
    public function procesarDocumento(string $absolutePath, ?int $documentoId = null): array
    {
        $url = $this->baseUrl . '/api/ocr/documento';

        $payload = [
            'pdf_path'     => $absolutePath,
            'idioma'       => $this->defaultLang,
            'documento_id' => $documentoId,
        ];

        $response = Http::timeout(180)->post($url, $payload);

        if (!$response->ok()) {
            $body = $response->body();
            throw new \RuntimeException(
                'Error en OCR service: HTTP ' . $response->status() . ' ' . $body
            );
        }

        $data = $response->json();

        if (!is_array($data) || !($data['ok'] ?? false)) {
            throw new \RuntimeException('Servicio OCR devolvió ok=false o respuesta inválida');
        }

        return $data;
    }
}
