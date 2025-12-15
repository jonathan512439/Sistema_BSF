-- =====================================================
-- ANÁLISIS DE BASE DE DATOS ACTUAL BSF
-- Ejecutar manualmente para ver estado actual
-- =====================================================

USE bsf_core;

-- 1. SECCIONES ACTUALES CON CONTEO DE DOCUMENTOS
SELECT 
    s.id,
    s.nombre as seccion,
    COUNT(DISTINCT d.id) as documentos_count,
    COUNT(DISTINCT sub.id) as subsecciones_count
FROM secciones s
LEFT JOIN subsecciones sub ON s.id = sub.seccion_id
LEFT JOIN documentos d ON s.id = d.seccion_id
GROUP BY s.id, s.nombre
ORDER BY s.id;

-- 2. SUBSECCIONES ACTUALES CON CONTEO
SELECT 
    sub.id,
    s.nombre as seccion,
    sub.nombre as subseccion,
    sub.seccion_id,
    COUNT(d.id) as documentos_count
FROM subsecciones sub
LEFT JOIN secciones s ON sub.seccion_id = s.id
LEFT JOIN documentos d ON sub.id = d.subseccion_id
GROUP BY sub.id
ORDER BY sub.seccion_id, sub.id;

-- 3. TIPOS DE DOCUMENTO ACTUALES CON CONTEO
SELECT 
    t.id,
    t.nombre as tipo,
    COUNT(d.id) as documentos_count
FROM tipos_documento t
LEFT JOIN documentos d ON t.id = d.tipo_documento_id
GROUP BY t.id
ORDER BY t.id;

-- 4. DOCUMENTOS SIN SECCIÓN (ESTO NO DEBERÍA EXISTIR)
SELECT COUNT(*) as docs_sin_seccion
FROM documentos
WHERE seccion_id IS NULL;

-- 5. DOCUMENTOS SIN SUBSECCIÓN
SELECT COUNT(*) as docs_sin_subseccion
FROM documentos
WHERE subseccion_id IS NULL;

-- 6. DOCUMENTOS SIN TIPO
SELECT COUNT(*) as docs_sin_tipo
FROM documentos
WHERE tipo_documento_id IS NULL;

-- 7. TOTAL DE DOCUMENTOS
SELECT COUNT(*) as total_documentos FROM documentos;
