<?php

// Script de diagnóstico para thumbnails

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DIAGNÓSTICO DE THUMBNAILS ===\n\n";

// 1. Verificar Imagick
echo "1. Imagick:\n";
if (extension_loaded('imagick')) {
    echo "   ✓ Extensión cargada\n";
    $imagick = new Imagick();
    $version = $imagick->getVersion();
    echo "   Versión: " . print_r($version, true) . "\n";
} else {
    echo "   ✗ Extensión NO cargada\n";
}

// 2. Verificar directorio de thumbnails
echo "\n2. Directorio de thumbnails:\n";
$thumbDir = storage_path('app/public/thumbnails');
echo "   Ruta: $thumbDir\n";
if (is_dir($thumbDir)) {
    echo "   ✓ Directorio existe\n";
    echo "   Permisos: " . substr(sprintf('%o', fileperms($thumbDir)), -4) . "\n";
} else {
    echo "   ✗ Directorio NO existe\n";
    echo "   Intentando crear...\n";
    if (mkdir($thumbDir, 0755, true)) {
        echo "   ✓ Directorio creado\n";
    } else {
        echo "   ✗ No se pudo crear\n";
    }
}

// 3. Verificar un documento de ejemplo
echo "\n3. Verificando documentos:\n";
$doc = \App\Models\Documento::with(['archivos.almacen'])->first();
if ($doc) {
    echo "   Documento ID: {$doc->id}\n";
    $archivo = $doc->archivos()->where('version', 1)->with('almacen')->first();
    
    if ($archivo && $archivo->almacen) {
        $basePath = rtrim($archivo->almacen->base_path, '/\\');
        $relativePath = ltrim($archivo->ruta_relativa, '/\\');
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
        $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
        
        echo "   Almacén: {$archivo->almacen->nombre}\n";
        echo "   Base path: $basePath\n";
        echo "   Ruta relativa: $relativePath\n";
        echo "   Ruta completa: $fullPath\n";
        
        if (file_exists($fullPath)) {
            echo "   ✓ Archivo PDF existe\n";
            echo "   Tamaño: " . filesize($fullPath) . " bytes\n";
            
            // Intentar generar thumbnail
            try {
                $cacheKey = "thumb_{$doc->id}_v{$archivo->version}";
                $cachePath = $thumbDir . DIRECTORY_SEPARATOR . $cacheKey . '.jpg';
                
                echo "\n   Generando thumbnail de prueba...\n";
                $imagick = new \Imagick();
                $imagick->setResolution(150, 150);
                $imagick->readImage($fullPath . '[0]');
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $imagick->thumbnailImage(280, 400, true);
                $imagick->writeImage($cachePath);
                $imagick->clear();
                $imagick->destroy();
                
                echo "   ✓ Thumbnail generado: $cachePath\n";
                echo "   Tamaño thumbnail: " . filesize($cachePath) . " bytes\n";
                
            } catch (\Exception $e) {
                echo "   ✗ Error al generar thumbnail: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "   ✗ Archivo PDF NO existe en: $fullPath\n";
        }
    } else {
        echo "   ✗ No tiene archivo o almacén\n";
    }
} else {
    echo "   ✗ No hay documentos en la BD\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
