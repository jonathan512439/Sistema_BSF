<?php
/**
 * Script de prueba para generación de reportes PDF
 * Ejecutar desde la raíz del proyecto: php test-reports.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\ReportController;

echo "====================================\n";
echo "  PRUEBAS DE GENERACION DE REPORTES\n";
echo "====================================\n\n";

$controller = new ReportController();

// Tipos de reportes a probar
$reportTypes = [
    'by-status',
    'audit-comprehensive',
    'audit-detailed',
    'user-activity',
    'document-inventory'
];

foreach ($reportTypes as $type) {
    echo "Probando reporte: $type\n";

    try {
        // Crear request simulado
        $request = Request::create('/api/reports/generate', 'POST', [
            'type' => $type,
            'format' => 'pdf',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-15'
        ]);

        // Generar reporte
        $response = $controller->generate($request);

        // Verificar respuesta
        $statusCode = $response->status();

        if ($statusCode === 200) {
            // Obtener contenido
            $content = $response->getContent();

            // Verificar que es un PDF válido (empieza con %PDF)
            if (substr($content, 0, 4) === '%PDF') {
                echo "✓ PDF generado correctamente ($type)\n";
                echo "  Tamaño: " . strlen($content) . " bytes\n";

                // Guardar para inspección manual
                $filename = storage_path("app/test_reporte_{$type}.pdf");
                file_put_contents($filename, $content);
                echo "  Guardado en: $filename\n";
            } else {
                echo "✗ ERROR: La respuesta no es un PDF válido ($type)\n";
                echo "  Primeros bytes: " . substr($content, 0, 50) . "\n";
            }
        } else {
            echo "✗ ERROR: Status code $statusCode ($type)\n";
            echo "  Respuesta: " . substr($response->getContent(), 0, 200) . "\n";
        }

    } catch (\Throwable $e) {
        echo "✗ EXCEPTION ($type): " . $e->getMessage() . "\n";
        echo "  Línea: " . $e->getLine() . "\n";
        echo "  Archivo: " . $e->getFile() . "\n";
    }

    echo "\n";
}

echo "====================================\n";
echo "  PRUEBAS COMPLETADAS\n";
echo "====================================\n";
echo "Los PDFs generados están en storage/app/\n";
echo "Ábrelos con un visor PDF para verificar:\n";
echo "  - Logo en la esquina\n";
echo "  - Encabezados y pies de página\n";
echo "  - Datos correctos\n";
echo "  - Paginación funcionando\n";
