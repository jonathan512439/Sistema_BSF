<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PDFGeneratorService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Genera un PDF a partir de un array de imágenes
     *
     * @param array $images Array de UploadedFile
     * @param array $rotations Array de rotaciones (0, 90, 180, 270) por índice
     * @param string $paperSize Tamaño de papel (A4, Letter, Legal)
     * @param string $orientation Orientación (portrait, landscape)
     * @param int $quality Calidad JPEG (1-100)
     * @return string Ruta del PDF generado
     */
    public function generateFromImages(
        array $images,
        array $rotations = [],
        string $paperSize = 'A4',
        string $orientation = 'portrait',
        int $quality = 85
    ): string {
        // SOLUCIÓN: Aumentar memoria a 2GB
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '600');

        // Crear PDF
        $pdf = new \FPDF($orientation, 'mm', $paperSize);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        // Obtener dimensiones del papel
        $dimensions = $this->getPaperDimensions($paperSize, $orientation);

        // Procesar cada imagen
        foreach ($images as $idx => $imageFile) {
            // Cargar imagen
            $image = $this->imageManager->read($imageFile->path());

            // Rotar si es necesario
            $rotation = $rotations[$idx] ?? 0;
            if ($rotation != 0) {
                $image->rotate(-$rotation); // Intervention usa sentido antihorario
            }

            // Redimensionar para ajustar al papel manteniendo aspect ratio
            $image = $this->resizeToFit($image, $dimensions['width'], $dimensions['height']);

            // OPTIMIZACIÓN: Comprimir agresivamente (60-70%)
            $optimizedQuality = max(60, min(70, $quality));

            // Guardar temporalmente como JPEG
            $tempPath = storage_path('app/temp/pdf_gen_' . uniqid() . '_' . $idx . '.jpg');
            $this->ensureTempDirectory();
            $image->toJpeg($optimizedQuality)->save($tempPath);

            // Agregar página al PDF
            $pdf->AddPage();

            // Centrar imagen en la página (USAR 150 DPI en lugar de 96)
            $imgInfo = getimagesize($tempPath);
            $imgWidthMm = ($imgInfo[0] * 25.4) / 150; // 150 DPI = menos memoria
            $imgHeightMm = ($imgInfo[1] * 25.4) / 150;

            $x = ($dimensions['width'] - $imgWidthMm) / 2;
            $y = ($dimensions['height'] - $imgHeightMm) / 2;

            $pdf->Image($tempPath, $x, $y, $imgWidthMm, $imgHeightMm);

            // CRÍTICO: Limpiar archivo temporal y liberar memoria
            @unlink($tempPath);
            unset($image);

            // Garbage collection cada 2 imágenes
            if (($idx + 1) % 2 === 0) {
                gc_collect_cycles();
            }
        }

        // CRÍTICO: Liberar memoria final
        gc_collect_cycles();

        // Guardar PDF
        $pdfFilename = 'generated_' . Str::random(16) . '.pdf';
        $pdfPath = storage_path('app/temp/' . $pdfFilename);
        $pdf->Output('F', $pdfPath);

        return $pdfPath;
    }

    /**
     * Redimensiona imagen para ajustar al papel manteniendo aspect ratio
     */
    private function resizeToFit($image, float $maxWidth, float $maxHeight)
    {
        // Convertir mm a píxeles (usamos 96 DPI como estándar web)
        $maxWidthPx = ($maxWidth * 96) / 25.4;
        $maxHeightPx = ($maxHeight * 96) / 25.4;

        // Obtener dimensiones actuales
        $currentWidth = $image->width();
        $currentHeight = $image->height();

        // Calcular ratio
        $widthRatio = $maxWidthPx / $currentWidth;
        $heightRatio = $maxHeightPx / $currentHeight;
        $ratio = min($widthRatio, $heightRatio);

        // Si la imagen es más pequeña, no la agrandamos
        if ($ratio > 1) {
            return $image;
        }

        // Redimensionar
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

        // Intercambiar si es landscape
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

    /**
     * Limpia archivos temporales antiguos (más de 1 hora)
     */
    public function cleanupOldTempFiles(): void
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 3600) { // 1 hora
                    @unlink($file);
                }
            }
        }
    }
}
