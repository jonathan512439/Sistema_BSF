<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$versiones = DB::table('documento_versiones')
    ->where('documento_id', 160)
    ->select('id', 'version_numero', 'archivo_path', 'numero_paginas', 'es_version_actual', 'created_at')
    ->orderBy('version_numero')
    ->get();

echo "Versiones del documento 160:\n";
foreach ($versiones as $v) {
    echo sprintf(
        "V%d - Páginas: %s - Actual: %s - Path: %s\n",
        $v->version_numero,
        $v->numero_paginas ?? 'N/A',
        $v->es_version_actual ? 'SÍ' : 'No',
        $v->archivo_path ?? 'N/A'
    );

    if ($v->archivo_path) {
        $fullPath = storage_path('app/' . $v->archivo_path);
        $exists = file_exists($fullPath) ? 'EXISTE' : 'NO EXISTE';
        echo "  -> Archivo: $exists en $fullPath\n";
    }
}
