<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PDFCombinerService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Combina un PDF existente con nuevas imágenes
     * 
     * @param string $rutaPdfExistente Ruta absoluta al PDF actual
     * @param array $nuevasImagenes Array de UploadedFile
     * @return string Ruta del PDF combinado
     */
    public function combinarPdfConImagenes(string $rutaPdfExistente, array $nuevasImagenes): string
    {
        // Crear instancia FPDI
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        // PASO 1: Importar todas las páginas del PDF existente
        $paginasExistentes = $pdf->setSourceFile($rutaPdfExistente);

        for ($i = 1; $i <= $paginasExistentes; $i++) {
            // Importar página
            $templateId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($templateId);

            // Agregar página con misma orientación
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);

            // Usar template (copiar página existente)
            $pdf->useTemplate($templateId);
        }

        // PASO 2: Agregar nuevas imágenes como páginas adicionales
        $paperSize = 'A4'; // Usar mismo tamaño que páginas anteriores
        $orientation = 'portrait';
        $quality = 85;

        foreach ($nuevasImagenes as $idx => $imageFile) {
            // Cargar imagen
            $image = $this->imageManager->read($imageFile->path());

            // Redimensionar para ajustar a A4
            $dimensions = $this->getPaperDimensions($paperSize, $orientation);
            $image = $this->resizeToFit($image, $dimensions['width'], $dimensions['height']);

            // Guardar temporalmente como JPEG
            $tempPath = storage_path('app/temp/pdf_combine_' . uniqid() . '_' . $idx . '.jpg');
            $this->ensureTempDirectory();
            $image->toJpeg($quality)->save($tempPath);

            // Agregar página al PDF
            $pdf->AddPage();

            // Centrar imagen en la página
            $imgInfo = getimagesize($tempPath);
            $imgWidthMm = ($imgInfo[0] * 25.4) / 96;
            $imgHeightMm = ($imgInfo[1] * 25.4) / 96;

            $x = ($dimensions['width'] - $imgWidthMm) / 2;
            $y = ($dimensions['height'] - $imgHeightMm) / 2;

            $pdf->Image($tempPath, $x, $y, $imgWidthMm, $imgHeightMm);

            // Limpiar archivo temporal
            @unlink($tempPath);
        }

        // Guardar PDF combinado
        $pdfFilename = 'combined_' . Str::random(16) . '.pdf';
        $pdfPath = storage_path('app/temp/' . $pdfFilename);
        $pdf->Output('F', $pdfPath);

        return $pdfPath;
    }

    /**
     * Redimensiona imagen para ajustar al papel manteniendo aspect ratio
     */
    private function resizeToFit($image, float $maxWidth, float $maxHeight)
    {
        $maxWidthPx = ($maxWidth * 96) / 25.4;
        $maxHeightPx = ($maxHeight * 96) / 25.4;

        $currentWidth = $image->width();
        $currentHeight = $image->height();

        $widthRatio = $maxWidthPx / $currentWidth;
        $heightRatio = $maxHeightPx / $currentHeight;
        $ratio = min($widthRatio, $heightRatio);

        if ($ratio > 1) {
            return $image;
        }

        $newWidth = (int) ($currentWidth * $ratio);
        $newHeight = (int) ($currentHeight * $ratio);

        $image->scale($newWidth, $newHeight);

        return $image;
    }

    /**
     * Obtiene las dimensiones del papel en milímetros
     */
    private function getPaperDimensions(string $paperSize, string $orientation): array
    {
        $sizes = [
            'A4' => ['width' => 210, 'height' => 297],
            'Letter' => ['width' => 216, 'height' => 279],
            'Legal' => ['width' => 216, 'height' => 356],
        ];

        $dimensions = $sizes[$paperSize] ?? $sizes['A4'];

        if ($orientation === 'landscape') {
            return [
                'width' => $dimensions['height'],
                'height' => $dimensions['width'],
            ];
        }

        return $dimensions;
    }

    /**
     * Asegura que exista el directorio temporal
     */
    private function ensureTempDirectory(): void
    {
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    }
}
