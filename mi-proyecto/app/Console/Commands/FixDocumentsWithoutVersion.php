<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixDocumentsWithoutVersion extends Command
{
    protected $signature = 'documents:fix-versions {--dry-run : Simular sin hacer cambios}';
    protected $description = 'Crea versiones iniciales (V1) para documentos sin versión en documento_versiones';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 MODO SIMULACIÓN - No se harán cambios');
        }

        try {
            // Contar documentos sin versión en documento_versiones
            $count = DB::select("
                SELECT COUNT(DISTINCT d.id) as total
                FROM documentos d
                INNER JOIN documentos_archivos da ON d.id = da.documento_id AND da.version = 1
                LEFT JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
                WHERE dv.id IS NULL 
                AND d.deleted_at IS NULL
            ")[0]->total;

            if ($count == 0) {
                $this->info('✅ No hay documentos sin versión');
                return 0;
            }

            $this->info("📋 Encontrados {$count} documentos sin versión inicial");

            if (!$dryRun) {
                $this->info('🔨 Creando versiones iniciales...');

                // Crear versiones con TODOS los campos necesarios
                $inserted = DB::statement("
                    INSERT INTO documento_versiones 
                    (documento_id, version_numero, titulo, descripcion, tipo_documento_id, 
                     seccion_id, subseccion_id, gestion_id, estado, is_confidential,
                     archivo_path, archivo_nombre, archivo_size_bytes, archivo_mime_type, archivo_hash,
                     numero_paginas, version_tipo, version_motivo, es_version_actual, creado_por, creado_en)
                    SELECT 
                        d.id,
                        1,
                        d.titulo,
                        d.descripcion,
                        d.tipo_documento_id,
                        d.seccion_id,
                        d.subseccion_id,
                        d.gestion_id,
                        d.estado,
                        d.is_confidential,
                        da.ruta_relativa,
                        SUBSTRING_INDEX(da.ruta_relativa, '/', -1),
                        da.bytes,
                        da.mime,
                        da.sha256,
                        NULL, -- numero_paginas se calculará si es necesario
                        'manual',
                        'Versión inicial - Migración automática',
                        1,
                        1,
                        NOW()
                    FROM documentos d
                    INNER JOIN documentos_archivos da ON d.id = da.documento_id AND da.version = 1
                    LEFT JOIN documento_versiones dv ON d.id = dv.documento_id
                    WHERE dv.id IS NULL
                    AND d.deleted_at IS NULL
                ");

                $this->info('✅ Versiones creadas en documento_versiones');

                // Actualizar version_actual y total_versiones en documentos
                $updated = DB::statement("
                    UPDATE documentos d
                    INNER JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
                    SET d.version_actual = 1,
                        d.total_versiones = 1
                    WHERE (d.version_actual IS NULL OR d.version_actual = 0 OR d.total_versiones IS NULL OR d.total_versiones = 0)
                    AND d.deleted_at IS NULL
                ");

                $this->info('✅ Campos version_actual y total_versiones actualizados');
                $this->info("✅ {$count} documentos corregidos exitosamente");

                Log::info("Comando documents:fix-versions ejecutado: {$count} documentos corregidos");
            } else {
                $this->info("📊 {$count} documentos necesitan corrección");
                $this->info("💡 Ejecuta sin --dry-run para aplicar los cambios");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("Error en documents:fix-versions: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
