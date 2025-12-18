-- =====================================================
-- SCRIPT: Crear Versiones Iniciales (V1) para Documentos sin Versión
-- Fecha: 2025-12-18
-- Propósito: Crear entradas en documento_versiones para todos los documentos
--           que no tienen una versión V1 registrada
-- =====================================================

-- PASO 1: Verificar cuántos documentos necesitan versión inicial
SELECT 
    COUNT(DISTINCT d.id) as documentos_sin_version,
    'Documentos que necesitan versión V1' as descripcion
FROM documentos d
INNER JOIN documentos_archivos da ON d.id = da.documento_id AND da.version = 1
LEFT JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
WHERE dv.id IS NULL
AND d.deleted_at IS NULL;

-- PASO 2: Ver lista de documentos afectados (para verificación)
SELECT 
    d.id,
    d.titulo,
    d.estado,
    da.ruta_relativa,
    da.bytes,
    d.created_at
FROM documentos d
INNER JOIN documentos_archivos da ON d.id = da.documento_id AND da.version = 1
LEFT JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
WHERE dv.id IS NULL
AND d.deleted_at IS NULL
ORDER BY d.id;

-- PASO 3: Crear versiones V1 con TODOS los campos necesarios
-- IMPORTANTE: Ejecutar solo después de verificar los pasos anteriores
INSERT INTO documento_versiones 
(
    documento_id, 
    version_numero, 
    titulo, 
    descripcion, 
    tipo_documento_id, 
    seccion_id, 
    subseccion_id, 
    gestion_id, 
    estado, 
    is_confidential,
    archivo_path, 
    archivo_nombre, 
    archivo_size_bytes, 
    archivo_mime_type, 
    archivo_hash,
    numero_paginas,
    version_tipo, 
    version_motivo, 
    es_version_actual, 
    creado_por, 
    creado_en
)
SELECT 
    d.id,                                           -- documento_id
    1,                                              -- version_numero
    d.titulo,                                       -- titulo
    d.descripcion,                                  -- descripcion
    d.tipo_documento_id,                           -- tipo_documento_id
    d.seccion_id,                                  -- seccion_id
    d.subseccion_id,                               -- subseccion_id
    d.gestion_id,                                  -- gestion_id
    d.estado,                                       -- estado
    COALESCE(d.is_confidential, 0),                -- is_confidential
    da.ruta_relativa,                              -- archivo_path
    SUBSTRING_INDEX(da.ruta_relativa, '/', -1),    -- archivo_nombre (extraer nombre del path)
    da.bytes,                                       -- archivo_size_bytes
    da.mime,                                        -- archivo_mime_type
    da.sha256,                                      -- archivo_hash
    NULL,                                           -- numero_paginas (se calculará después si es necesario)
    'manual',                                       -- version_tipo
    'Versión inicial - Migración automática',      -- version_motivo
    1,                                              -- es_version_actual
    COALESCE(d.created_by, 1),                     -- creado_por (usuario 1 por defecto)
    COALESCE(d.created_at, NOW())                  -- creado_en
FROM documentos d
INNER JOIN documentos_archivos da ON d.id = da.documento_id AND da.version = 1
LEFT JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
WHERE dv.id IS NULL
AND d.deleted_at IS NULL;

-- PASO 4: Actualizar campos version_actual y total_versiones en documentos
UPDATE documentos d
INNER JOIN documento_versiones dv ON d.id = dv.documento_id AND dv.version_numero = 1
SET 
    d.version_actual = 1,
    d.total_versiones = 1
WHERE (
    d.version_actual IS NULL 
    OR d.version_actual = 0 
    OR d.total_versiones IS NULL 
    OR d.total_versiones = 0
)
AND d.deleted_at IS NULL;

-- PASO 5: Verificación final - Estos queries deben retornar 0 filas
SELECT 
    d.id,
    d.titulo,
    'SIN VERSION_ACTUAL' as problema
FROM documentos d
WHERE d.deleted_at IS NULL
AND (d.version_actual IS NULL OR d.version_actual = 0)
UNION ALL
SELECT 
    d.id,
    d.titulo,
    'SIN VERSION EN TABLA' as problema
FROM documentos d
LEFT JOIN documento_versiones dv ON d.id = dv.documento_id
WHERE d.deleted_at IS NULL
AND dv.id IS NULL;

-- PASO 6: Estadísticas finales
SELECT 
    'Total documentos activos' as metrica,
    COUNT(*) as valor
FROM documentos 
WHERE deleted_at IS NULL
UNION ALL
SELECT 
    'Documentos con versión V1' as metrica,
    COUNT(DISTINCT documento_id) as valor
FROM documento_versiones 
WHERE version_numero = 1
UNION ALL
SELECT 
    'Documentos con version_actual configurado' as metrica,
    COUNT(*) as valor
FROM documentos 
WHERE deleted_at IS NULL 
AND version_actual IS NOT NULL 
AND version_actual > 0;

-- =====================================================
-- NOTAS IMPORTANTES:
-- 1. Ejecutar los pasos en orden
-- 2. Verificar PASO 1 y PASO 2 antes de ejecutar PASO 3
-- 3. El PASO 5 debe retornar 0 filas si todo está correcto
-- =====================================================
