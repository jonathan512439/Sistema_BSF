<?php
require __DIR__ . '/vendor/autoload.php';

$pdfPath = 'D:\Jonathan\Desktop\INFORMATICA\INF3811-ISW2\BSF-docs\mi-proyecto\storage\app\documentos\160\versiones\doc_160_v5.pdf';

if (!file_exists($pdfPath)) {
    echo "❌ El archivo NO existe en: $pdfPath\n";
    exit(1);
}

echo "✅ El archivo EXISTE\n";
echo "Tamaño: " . number_format(filesize($pdfPath)) . " bytes\n";

// Contar páginas
try {
    $content = file_get_contents($pdfPath);
    preg_match_all("/\/Page\W/", $content, $matches);
    $numPages = count($matches[0]);
    echo "📄 Número de páginas: $numPages\n";

    if ($numPages == 7) {
        echo "✅ ¡CORRECTO! El PDF tiene 7 páginas como esperado\n";
    } else {
        echo "❌ ERROR: Se esperaban 7 páginas pero tiene $numPages\n";
    }
} catch (Exception $e) {
    echo "Error contando páginas: " . $e->getMessage() . "\n";
}
