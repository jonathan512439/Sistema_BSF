<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoArchivo;
use App\Models\DocumentoVersion;
use App\Models\DocumentoVersionCambio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class DocumentVersionService
{
    /**
     * Crear nueva versión cuando se sube un archivo nuevo/modificado
     * 
     * @param Documento $documento
     * @param UploadedFile $archivo
     * @param string $motivo
     * @param int|null $userId
     * @param string|null $ip
     * @param string|null $userAgent
     * @return DocumentoVersion
     * @throws \Exception
     */
    public function crearVersionConArchivo(
        Documento $documento,
        UploadedFile $archivo,
        string $motivo,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): DocumentoVersion {

        return DB::transaction(function () use ($documento, $archivo, $motivo, $userId, $ip, $userAgent) {

            // 1. Validar que se puede versionar
            $this->validarVersionable($documento);

            // 2. Obtener archivo actual (si existe)
            $archivoActual = DocumentoArchivo::where('documento_id', $documento->id)
                ->whereNull('deleted_at')
                ->orderBy('version', 'desc')
                ->first();

            $nuevaVersionArchivo = $archivoActual ? $archivoActual->version + 1 : 1;
            $numeroVersion = $documento->total_versiones + 1;

            // 3. Guardar archivo físico
            $almacenId = $archivoActual->almacen_id ?? 1; // Almacén por defecto
            $rutaRelativa = $this->guardarArchivo($archivo, $documento->id, $numeroVersion);
            $hash = hash_file('sha256', $archivo->getRealPath());

            // 4. Contar páginas del PDF (si aplica)
            $numeroPaginas = $this->contarPaginasPDF($archivo);

            // 5. Crear registro en documento_archivos
            $nuevoArchivo = DocumentoArchivo::create([
                'documento_id' => $documento->id,
                'almacen_id' => $almacenId,
                'ruta_relativa' => $rutaRelativa,
                'version' => $nuevaVersionArchivo,
                'bytes' => $archivo->getSize(),
                'mime' => $archivo->getMimeType(),
                'sha256' => $hash,
            ]);

            // 6. Detectar cambios en metadatos
            $cambiosMetadatos = $this->detectarCambiosMetadatos($documento);

            // 7. Crear snapshot en documento_versiones
            $version = DocumentoVersion::create([
                'documento_id' => $documento->id,
                'version_numero' => $numeroVersion,

                // Snapshot metadatos
                'titulo' => $documento->titulo,
                'descripcion' => $documento->descripcion,
                'tipo_documento_id' => $documento->tipo_documento_id,
                'seccion_id' => $documento->seccion_id,
                'subseccion_id' => $documento->subseccion_id,
                'gestion_id' => $documento->gestion_id,
                'estado' => $documento->estado,
                'is_confidential' => $documento->is_confidential,

                // Info del archivo
                'archivo_path' => $rutaRelativa,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'archivo_size_bytes' => $archivo->getSize(),
                'archivo_mime_type' => $archivo->getMimeType(),
                'archivo_hash' => $hash,

                // Metadatos versión
                'version_tipo' => 'manual',
                'version_motivo' => $motivo . ($numeroPaginas ? " | Páginas: {$numeroPaginas}" : ""),
                'es_version_actual' => true,

                // Auditoría
                'creado_por' => $userId ?? auth()->id(),
                'creado_en' => now(),
                'ip_origen' => $ip,
                'user_agent' => $userAgent,
            ]);

            // 8. Registrar cambios granulares
            if ($archivoActual) {
                $paginasAnterior = $this->obtenerNumeroPaginas($archivoActual);

                DocumentoVersionCambio::create([
                    'version_id' => $version->id,
                    'campo' => 'archivo_pdf',
                    'valor_anterior' => "Archivo v{$archivoActual->version} - {$paginasAnterior} páginas",
                    'valor_nuevo' => "Archivo v{$nuevoArchivo->version} - {$numeroPaginas} páginas",
                ]);

                DocumentoVersionCambio::create([
                    'version_id' => $version->id,
                    'campo' => 'numero_paginas',
                    'valor_anterior' => (string) $paginasAnterior,
                    'valor_nuevo' => (string) $numeroPaginas,
                ]);

                DocumentoVersionCambio::create([
                    'version_id' => $version->id,
                    'campo' => 'tamano_bytes',
                    'valor_anterior' => (string) $archivoActual->bytes,
                    'valor_nuevo' => (string) $archivo->getSize(),
                ]);
            }

            // Cambios de metadatos
            foreach ($cambiosMetadatos as $cambio) {
                DocumentoVersionCambio::create([
                    'version_id' => $version->id,
                    'campo' => $cambio['campo'],
                    'valor_anterior' => $cambio['anterior'],
                    'valor_nuevo' => $cambio['nuevo'],
                ]);
            }

            // 9. Marcar versiones anteriores como no actuales
            DocumentoVersion::where('documento_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->update(['es_version_actual' => false]);

            // 10. Actualizar documento principal
            $documento->update([
                'version_actual' => $numeroVersion,
                'total_versiones' => $numeroVersion,
            ]);

            // 11. Auditoría
            auditLog([
                'evento' => 'documento.version_created',
                'objeto_tipo' => 'documento',
                'objeto_id' => $documento->id,
                'payload_json' => json_encode([
                    'version_numero' => $numeroVersion,
                    'archivo_version' => $nuevaVersionArchivo,
                    'motivo' => $motivo,
                    'paginas_pdf' => $numeroPaginas,
                    'tamano_bytes' => $archivo->getSize(),
                    'hash' => substr($hash, 0, 16),
                    'estado_documento' => $documento->estado,
                ])
            ]);

            // 12. Log warning si es documento validado
            if ($documento->estado === 'validado') {
                Log::warning("Nueva versión creada en documento VALIDADO", [
                    'documento_id' => $documento->id,
                    'version_numero' => $numeroVersion,
                    'usuario_id' => $userId ?? auth()->id(),
                    'motivo' => $motivo
                ]);
            }

            return $version;
        });
    }

    /**
     * Crear versión inicial (V1) para documento recién creado
     * Este método se llama automáticamente al subir un documento nuevo
     * 
     * @param Documento $documento
     * @param DocumentoArchivo $archivo
     * @param string $rutaAbsoluta Ruta absoluta al archivo PDF en disco
     * @param int|null $userId
     * @return DocumentoVersion
     * @throws \Exception
     */
    public function crearVersionInicial(
        Documento $documento,
        DocumentoArchivo $archivo,
        string $rutaAbsoluta,
        ?int $userId = null
    ): DocumentoVersion {
        return DB::transaction(function () use ($documento, $archivo, $rutaAbsoluta, $userId) {

            // 1. Verificar que NO exista ya una versión para este documento
            $existeVersion = DocumentoVersion::where('documento_id', $documento->id)->exists();
            if ($existeVersion) {
                throw new \Exception("El documento #{$documento->id} ya tiene versiones registradas. No se puede crear versión inicial.");
            }

            // 2. Contar páginas del PDF
            $numeroPaginas = $this->contarPaginasPDFDesdeRuta($rutaAbsoluta);

            // 3. Crear versión 1 con snapshot completo
            $version = DocumentoVersion::create([
                'documento_id' => $documento->id,
                'version_numero' => 1,

                // Snapshot de metadatos
                'titulo' => $documento->titulo,
                'descripcion' => $documento->descripcion,
                'tipo_documento_id' => $documento->tipo_documento_id,
                'seccion_id' => $documento->seccion_id,
                'subseccion_id' => $documento->subseccion_id,
                'gestion_id' => $documento->gestion_id,
                'estado' => $documento->estado,
                'is_confidential' => $documento->is_confidential,

                // Info del archivo
                'archivo_path' => $archivo->ruta_relativa,
                'archivo_nombre' => basename($archivo->ruta_relativa),
                'archivo_size_bytes' => $archivo->bytes,
                'archivo_mime_type' => $archivo->mime,
                'archivo_hash' => $archivo->sha256,

                // Metadatos de versión
                'version_tipo' => 'manual',
                'version_motivo' => 'Versión inicial - Documento creado' . ($numeroPaginas ? " | Páginas: {$numeroPaginas}" : ""),
                'es_version_actual' => true,

                // Auditoría
                'creado_por' => $userId ?? auth()->id() ?? 1,
                'creado_en' => now(),
            ]);

            // 4. Actualizar documento principal
            $documento->update([
                'version_actual' => 1,
                'total_versiones' => 1,
            ]);

            // 5. Auditoría
            auditLog([
                'evento' => 'documento.version_inicial',
                'objeto_tipo' => 'documento',
                'objeto_id' => $documento->id,
                'payload_json' => json_encode([
                    'version_numero' => 1,
                    'paginas' => $numeroPaginas,
                    'tamano_bytes' => $archivo->bytes,
                    'hash' => substr($archivo->sha256, 0, 16),
                ])
            ]);

            Log::info("Versión inicial creada para documento #{$documento->id}", [
                'version_id' => $version->id,
                'paginas' => $numeroPaginas,
                'usuario_id' => $userId ?? auth()->id() ?? 1,
            ]);

            return $version;
        });
    }

    /**
     * Crear versión solo de metadatos (sin cambio de archivo)
     */
    public function crearVersionMetadatos(
        Documento $documento,
        array $cambios,
        string $motivo,
        ?int $userId = null
    ): DocumentoVersion {

        return DB::transaction(function () use ($documento, $cambios, $motivo, $userId) {

            $this->validarVersionable($documento);

            $numeroVersion = $documento->total_versiones + 1;

            // Obtener archivo actual para referenciar
            $archivoActual = DocumentoArchivo::where('documento_id', $documento->id)
                ->whereNull('deleted_at')
                ->orderBy('version', 'desc')
                ->first();

            // Crear snapshot
            $version = DocumentoVersion::create([
                'documento_id' => $documento->id,
                'version_numero' => $numeroVersion,
                'titulo' => $documento->titulo,
                'descripcion' => $documento->descripcion,
                'tipo_documento_id' => $documento->tipo_documento_id,
                'seccion_id' => $documento->seccion_id,
                'subseccion_id' => $documento->subseccion_id,
                'gestion_id' => $documento->gestion_id,
                'estado' => $documento->estado,
                'is_confidential' => $documento->is_confidential,
                'archivo_path' => $archivoActual->ruta_relativa ?? '',
                'archivo_nombre' => basename($archivoActual->ruta_relativa ?? 'sin_archivo.pdf'),
                'archivo_size_bytes' => $archivoActual->bytes ?? 0,
                'archivo_mime_type' => $archivoActual->mime ?? 'application/pdf',
                'archivo_hash' => $archivoActual->sha256 ?? '',
                'version_tipo' => 'automatica',
                'version_motivo' => $motivo,
                'es_version_actual' => true,
                'creado_por' => $userId ?? auth()->id(),
                'creado_en' => now(),
            ]);

            // Registrar cambios
            foreach ($cambios as $cambio) {
                DocumentoVersionCambio::create([
                    'version_id' => $version->id,
                    'campo' => $cambio['campo'],
                    'valor_anterior' => $cambio['anterior'],
                    'valor_nuevo' => $cambio['nuevo'],
                ]);
            }

            // Actualizar
            DocumentoVersion::where('documento_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->update(['es_version_actual' => false]);

            $documento->increment('total_versiones');
            $documento->update(['version_actual' => $numeroVersion]);

            // Auditoría
            auditLog([
                'evento' => 'documento.version_created',
                'objeto_tipo' => 'documento',
                'objeto_id' => $documento->id,
                'payload_json' => json_encode([
                    'version_numero' => $numeroVersion,
                    'tipo' => 'solo_metadatos',
                    'motivo' => $motivo,
                    'cambios' => $cambios
                ])
            ]);

            return $version;
        });
    }

    /**
     * Restaurar versión anterior
     */
    public function restaurarVersion(
        Documento $documento,
        int $versionNumero,
        string $motivo,
        ?int $userId = null
    ): bool {
        return DB::transaction(function () use ($documento, $versionNumero, $motivo, $userId) {

            $this->validarVersionable($documento);

            $versionAnterior = DocumentoVersion::where('documento_id', $documento->id)
                ->where('version_numero', $versionNumero)
                ->firstOrFail();

            // Copiar archivo de versión anterior
            $archivoRestaurado = $this->copiarArchivoDeVersion($versionAnterior, $documento);

            // Crear nueva versión con datos restaurados
            $numeroVersion = $documento->total_versiones + 1;

            $version = DocumentoVersion::create([
                'documento_id' => $documento->id,
                'version_numero' => $numeroVersion,
                'titulo' => $versionAnterior->titulo,
                'descripcion' => $versionAnterior->descripcion,
                'tipo_documento_id' => $versionAnterior->tipo_documento_id,
                'seccion_id' => $versionAnterior->seccion_id,
                'subseccion_id' => $versionAnterior->subseccion_id,
                'gestion_id' => $versionAnterior->gestion_id,
                'estado' => $versionAnterior->estado,
                'is_confidential' => $versionAnterior->is_confidential,
                'archivo_path' => $archivoRestaurado->ruta_relativa,
                'archivo_nombre' => $versionAnterior->archivo_nombre,
                'archivo_size_bytes' => $archivoRestaurado->bytes,
                'archivo_mime_type' => $archivoRestaurado->mime,
                'archivo_hash' => $archivoRestaurado->sha256,
                'version_tipo' => 'manual',
                'version_motivo' => "Restauración de versión {$versionNumero}: {$motivo}",
                'es_version_actual' => true,
                'creado_por' => $userId ?? auth()->id(),
                'creado_en' => now(),
            ]);

            // Actualizar documento
            $documento->update([
                'titulo' => $versionAnterior->titulo,
                'descripcion' => $versionAnterior->descripcion,
                'tipo_documento_id' => $versionAnterior->tipo_documento_id,
                'seccion_id' => $versionAnterior->seccion_id,
                'subseccion_id' => $versionAnterior->subseccion_id,
                'estado' => $versionAnterior->estado,
                'version_actual' => $numeroVersion,
                'total_versiones' => $numeroVersion,
            ]);

            DocumentoVersion::where('documento_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->update(['es_version_actual' => false]);

            // Auditoría
            auditLog([
                'evento' => 'documento.version_restored',
                'objeto_tipo' => 'documento',
                'objeto_id' => $documento->id,
                'payload_json' => json_encode([
                    'version_restaurada' => $versionNumero,
                    'nueva_version' => $numeroVersion,
                    'motivo' => $motivo
                ])
            ]);

            return true;
        });
    }

    /**
     * Comparar dos versiones
     */
    public function compararVersiones(int $versionId1, int $versionId2): array
    {
        $v1 = DocumentoVersion::findOrFail($versionId1);
        $v2 = DocumentoVersion::findOrFail($versionId2);

        $cambios = [];
        $campos = ['titulo', 'descripcion', 'tipo_documento_id', 'seccion_id', 'estado', 'is_confidential'];

        foreach ($campos as $campo) {
            if ($v1->$campo !== $v2->$campo) {
                $cambios[] = [
                    'campo' => $campo,
                    'version_' . $v1->version_numero => $v1->$campo,
                    'version_' . $v2->version_numero => $v2->$campo,
                ];
            }
        }

        // Comparar archivos
        if ($v1->archivo_hash !== $v2->archivo_hash) {
            $cambios[] = [
                'campo' => 'archivo',
                'version_' . $v1->version_numero => [
                    'nombre' => $v1->archivo_nombre,
                    'tamano' => $v1->archivo_size_bytes,
                    'hash' => substr($v1->archivo_hash, 0, 16)
                ],
                'version_' . $v2->version_numero => [
                    'nombre' => $v2->archivo_nombre,
                    'tamano' => $v2->archivo_size_bytes,
                    'hash' => substr($v2->archivo_hash, 0, 16)
                ],
            ];
        }

        return $cambios;
    }

    // ===== MÉTODOS PRIVADOS / HELPERS =====

    /**
     * Validar que el documento puede ser versionado
     */
    private function validarVersionable(Documento $documento): void
    {
        // Regla 1: Versionado debe estar habilitado
        if (!$documento->versionado_habilitado) {
            throw new \Exception('El versionado está deshabilitado para este documento');
        }

        // Regla 2: SELLADO/CUSTODIO = INMUTABLE (bloqueado)
        if (in_array($documento->estado, ['sellado', 'custodio'])) {
            throw new \Exception(
                'No se pueden crear versiones de documentos sellados o en custodia. ' .
                'El sellado certifica la inmutabilidad del documento. ' .
                'Si necesita agregar información, cree un nuevo documento como anexo.'
            );
        }

        // Regla 3: VALIDADO = Advertencia (permitido pero registrado)
        // No lanzar excepción, solo advertir en logs (ya se hace en crearVersionConArchivo)
    }

    /**
     * Guardar archivo físico
     */
    private function guardarArchivo(\Illuminate\Http\UploadedFile|\Illuminate\Http\File $archivo, int $documentoId, int $versionNumero): string
    {
        // Obtener extensión según el tipo de archivo
        if ($archivo instanceof \Illuminate\Http\UploadedFile) {
            $extension = $archivo->getClientOriginalExtension();
        } else {
            $extension = $archivo->getExtension();
        }

        $nombreArchivo = "doc_{$documentoId}_v{$versionNumero}.{$extension}";
        $ruta = "documentos/{$documentoId}/versiones/{$nombreArchivo}";

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));

        return $ruta;
    }

    /**
     * Contar páginas de un PDF (acepta UploadedFile o ruta)
     */
    private function contarPaginasPDF($archivo): ?int
    {
        // Si es UploadedFile, obtener ruta temporal
        if ($archivo instanceof UploadedFile) {
            if ($archivo->getMimeType() !== 'application/pdf') {
                return null;
            }
            $path = $archivo->getRealPath();
        } else {
            // Asumimos que es una ruta de archivo
            $path = $archivo;
        }

        return $this->contarPaginasPDFDesdeRuta($path);
    }

    /**
     * Contar páginas de un PDF desde ruta absoluta
     */
    private function contarPaginasPDFDesdeRuta(string $path): ?int
    {
        if (!file_exists($path)) {
            Log::warning("No se pudo contar páginas: archivo no existe", ['path' => $path]);
            return null;
        }

        try {
            // Método 1: Usar pdfinfo si está disponible (más confiable)
            $output = shell_exec("pdfinfo \"{$path}\" 2>&1 | grep Pages");

            if ($output && preg_match('/Pages:\s+(\d+)/', $output, $matches)) {
                return (int) $matches[1];
            }

            // Método 2: Alternativa con expresión regular
            $content = file_get_contents($path);
            preg_match_all("/\/Page\W/", $content, $matches);
            $pageCount = count($matches[0]);

            return $pageCount > 0 ? $pageCount : null;

        } catch (\Exception $e) {
            Log::warning("No se pudo contar páginas del PDF: " . $e->getMessage(), [
                'path' => $path,
            ]);
            return null;
        }
    }

    /**
     * Obtener número de páginas de un archivo ya guardado
     */
    private function obtenerNumeroPaginas(DocumentoArchivo $archivo): int
    {
        $fullPath = storage_path('app/' . $archivo->ruta_relativa);

        if (!file_exists($fullPath)) {
            return 0;
        }

        try {
            $output = shell_exec("pdfinfo \"{$fullPath}\" 2>&1 | grep Pages");

            if ($output && preg_match('/Pages:\s+(\d+)/', $output, $matches)) {
                return (int) $matches[1];
            }

            $content = file_get_contents($fullPath);
            preg_match_all("/\/Page\W/", $content, $matches);
            return count($matches[0]) ?: 0;

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Detectar cambios en metadatos del documento
     */
    private function detectarCambiosMetadatos(Documento $documento): array
    {
        $cambios = [];
        $original = $documento->getOriginal();
        $camposObservar = ['titulo', 'descripcion', 'tipo_documento_id', 'seccion_id', 'estado', 'is_confidential'];

        foreach ($camposObservar as $campo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $documento->$campo) {
                $cambios[] = [
                    'campo' => $campo,
                    'anterior' => $original[$campo],
                    'nuevo' => $documento->$campo,
                ];
            }
        }

        return $cambios;
    }

    /**
     * Copiar archivo de una versión anterior
     */
    private function copiarArchivoDeVersion(DocumentoVersion $version, Documento $documento): DocumentoArchivo
    {
        // Copiar archivo de la versión antigua
        $archivoOrigen = $version->archivo_path;
        $nuevoNumeroVersion = DocumentoArchivo::where('documento_id', $documento->id)
            ->max('version') + 1;

        $extension = pathinfo($version->archivo_nombre, PATHINFO_EXTENSION);
        $nombreNuevo = "doc_{$documento->id}_v{$nuevoNumeroVersion}_restaurado.{$extension}";
        $rutaNueva = "documentos/{$documento->id}/versiones/{$nombreNuevo}";

        Storage::disk('local')->copy($archivoOrigen, $rutaNueva);

        // Crear registro
        return DocumentoArchivo::create([
            'documento_id' => $documento->id,
            'almacen_id' => 1,
            'ruta_relativa' => $rutaNueva,
            'version' => $nuevoNumeroVersion,
            'bytes' => $version->archivo_size_bytes,
            'mime' => $version->archivo_mime_type,
            'sha256' => $version->archivo_hash,
        ]);
    }

    /**
     * Agrega páginas desde imágenes al documento (NUEVO)
     * Combina PDF actual + imágenes = nueva versión completa
     */
    public function agregarPaginasDesdeImagenes(
        int $documentoId,
        array $imagenes,
        string $motivo,
        int $userId,
        string $ip,
        string $userAgent
    ): DocumentoVersion {
        $documento = Documento::findOrFail($documentoId);
        $pdfCombiner = app(PDFCombinerService::class);

        return DB::transaction(function () use ($documento, $imagenes, $motivo, $userId, $ip, $userAgent, $pdfCombiner) {


            // 1. Validar
            $this->validarVersionable($documento);

            // 2. Obtener versión actual
            $versionActual = DocumentoVersion::where('documento_id', $documento->id)
                ->where('es_version_actual', true)
                ->first();

            if (!$versionActual) {
                throw new \Exception("El documento #{$documento->id} no tiene una versión actual. Debe tener al menos una ");
            }

            // Validar que la versión actual tenga archivo_path
            if (!$versionActual->archivo_path) {
                throw new \Exception("La versión actual del documento #{$documento->id} no tiene archivo_path. Esto puede ocurrir si la versión se creó sin archivo físico.");
            }

            $rutaPdfActual = storage_path('app/' . $versionActual->archivo_path);

            if (!file_exists($rutaPdfActual)) {
                throw new \Exception("No se encontró el archivo físico del PDF en: {$rutaPdfActual}. Verifique que el archivo no haya sido movido o eliminado del almacenamiento.");
            }

            // 3. Contar páginas
            $paginasAnteriores = $this->contarPaginasPDF($rutaPdfActual);
            $paginasNuevas = count($imagenes);
            $paginasTotal = $paginasAnteriores + $paginasNuevas;

            // 4. Combinar PDF + imágenes
            $pdfCombinado = $pdfCombiner->combinarPdfConImagenes($rutaPdfActual, $imagenes);

            // 5. Preparar nueva versión
            $numeroVersion = $documento->total_versiones + 1;
            $hash = hash_file('sha256', $pdfCombinado);
            $tamanoBytes = filesize($pdfCombinado);

            // 6. Guardar archivo permanente
            $rutaRelativa = $this->guardarArchivo(
                new \Illuminate\Http\File($pdfCombinado),
                $documento->id,
                $numeroVersion
            );

            // 7. Crear versión (sin DocumentoArchivo - datos van directo en version)
            $motivoCompleto = sprintf(
                "%s | Páginas: %d → %d (+%d)",
                $motivo,
                $paginasAnteriores,
                $paginasTotal,
                $paginasNuevas
            );

            $version = DocumentoVersion::create([
                'documento_id' => $documento->id,
                'version_numero' => $numeroVersion,
                'titulo' => $versionActual->titulo,
                'descripcion' => $versionActual->descripcion,
                'tipo_documento_id' => $versionActual->tipo_documento_id,
                'seccion_id' => $versionActual->seccion_id,
                'subseccion_id' => $versionActual->subseccion_id,
                'gestion_id' => $versionActual->gestion_id,
                'estado' => $versionActual->estado,
                'is_confidential' => $versionActual->is_confidential,
                // Datos del archivo directamente en la versión
                'archivo_path' => $rutaRelativa,
                'archivo_nombre' => "doc_{$documento->id}_v{$numeroVersion}.pdf",
                'archivo_size_bytes' => $tamanoBytes,
                'archivo_mime_type' => 'application/pdf',
                'archivo_hash' => $hash,
                'numero_paginas' => $paginasTotal,
                // Metadatos de versión
                'version_tipo' => 'manual',
                'version_motivo' => $motivoCompleto,
                'es_version_actual' => false,
                'creado_por' => $userId,
                'creado_en' => now(),
                'ip_origen' => $ip,
                'user_agent' => $userAgent,
            ]);

            // 8. Crear cambio
            DocumentoVersionCambio::create([
                'version_id' => $version->id,
                'campo' => 'numero_paginas',
                'valor_anterior' => (string) $paginasAnteriores,
                'valor_nuevo' => (string) $paginasTotal,
            ]);

            // 9. Marcar como actual
            DocumentoVersion::where('documento_id', $documento->id)
                ->where('id', '!=', $version->id)
                ->update(['es_version_actual' => false]);

            $version->es_version_actual = true;
            $version->save();

            // 11. Actualizar documento principal para apuntar a la nueva versión
            $documento->version_actual = $numeroVersion;
            $documento->total_versiones = $numeroVersion;
            // La ruta del archivo se mantiene en documento_versiones.archivo_path, no en documentos
            $documento->save();

            // 12. Auditoría (DESHABILITADO - AuditService no existe)
            // app(AuditService::class)->append(
            //     evento: 'documento.version.paginas_agregadas',
            //     actorId: $userId,
            //     objetoTipo: 'documento',
            //     objetoId: $documento->id,
            //     payload: [
            //         'version_numero' => $numeroVersion,
            //         'paginas_anteriores' => $paginasAnteriores,
            //         'paginas_agregadas' => $paginasNuevas,
            //         'paginas_total' => $paginasTotal,
            //         'motivo' => $motivo
            //     ],
            //     ip: $ip,
            //     userAgent: $userAgent
            // );

            // 13. Limpiar temporal
            @unlink($pdfCombinado);

            return $version;
        });
    }
}
