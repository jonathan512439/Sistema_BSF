<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\PdfService;

try {
    echo "Probando PdfService...\n";

    $pdf = new PdfService();
    echo "✓ PdfService instanciado\n";

    $pdf->setReportInfo('Test Reporte', 'Prueba', 'Sistema');
    echo "✓ setReportInfo OK\n";

    $pdf->AddPage();
    echo "✓ AddPage OK\n";

    $pdf->addSectionTitle('Prueba');
    echo "✓ addSectionTitle OK\n";

    $headers = ['Col1', 'Col2'];
    $data = [['Valor1', 'Valor2']];
    $pdf->addTable($headers, $data);
    echo "✓ addTable OK\n";

    $content = $pdf->generate();
    echo "✓ generate() OK\n";
    echo "✓ Tamaño PDF: " . strlen($content) . " bytes\n";

    if (substr($content, 0, 4) === '%PDF') {
        echo "✅ PDF válido generado!\n";
    } else {
        echo "❌ No es un PDF válido\n";
        echo "Primeros bytes: " . substr($content, 0, 50) . "\n";
    }

} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
