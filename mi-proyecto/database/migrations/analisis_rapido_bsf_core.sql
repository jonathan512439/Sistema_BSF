-- =====================================================
-- ANÁLISIS RÁPIDO DE BASE DE DATOS BSF_CORE
-- Copiar y pegar todo en HeidiSQL / phpMyAdmin
-- =====================================================

USE bsf_core;

-- ========== RESUMEN GENERAL ==========
SELECT '=== RESUMEN GENERAL ===' as '';

SELECT 
    (SELECT COUNT(*) FROM secciones) as 'Total Secciones',
    (SELECT COUNT(*) FROM subsecciones) as 'Total Subsecciones',
    (SELECT COUNT(*) FROM tipos_documento) as 'Total Tipos',
    (SELECT COUNT(*) FROM documentos) as 'Total Documentos';

-- ========== SECCIONES ACTUALES ==========
SELECT '\n=== SECCIONES ACTUALES (con conteo de documentos) ===' as '';

SELECT 
    s.id,
    s.nombre as 'Sección',
    COUNT(DISTINCT d.id) as 'Documentos',
    COUNT(DISTINCT sub.id) as 'Subsecciones'
FROM secciones s
LEFT JOIN subsecciones sub ON s.id = sub.seccion_id
LEFT JOIN documentos d ON s.id = d.seccion_id
GROUP BY s.id, s.nombre
ORDER BY s.id;

-- ========== SUBSECCIONES ACTUALES ==========
SELECT '\n=== SUBSECCIONES ACTUALES (con conteo) ===' as '';

SELECT 
    sub.id,
    s.nombre as 'Sección Padre',
    sub.nombre as 'Subsección',
    COUNT(d.id) as 'Documentos'
FROM subsecciones sub
LEFT JOIN secciones s ON sub.seccion_id = s.id
LEFT JOIN documentos d ON sub.id = d.subseccion_id
GROUP BY sub.id, s.nombre, sub.nombre
ORDER BY sub.seccion_id, sub.id;

-- ========== TIPOS DE DOCUMENTO ACTUALES ==========
SELECT '\n=== TIPOS DE DOCUMENTO ACTUALES (con conteo) ===' as '';

SELECT 
    t.id,
    t.nombre as 'Tipo de Documento',
    COUNT(d.id) as 'Documentos'
FROM tipos_documento t
LEFT JOIN documentos d ON t.id = d.tipo_documento_id
GROUP BY t.id, t.nombre
ORDER BY t.id;

-- ========== VALIDACIÓN DE INTEGRIDAD ==========
SELECT '\n=== VALIDACIÓN DE INTEGRIDAD ===' as '';

SELECT 
    COUNT(*) as 'Total Documentos',
    SUM(CASE WHEN seccion_id IS NULL THEN 1 ELSE 0 END) as 'Sin Sección ⚠️',
    SUM(CASE WHEN subseccion_id IS NULL THEN 1 ELSE 0 END) as 'Sin Subsección ⚠️',
    SUM(CASE WHEN tipo_documento_id IS NULL THEN 1 ELSE 0 END) as 'Sin Tipo ⚠️'
FROM documentos;

-- ========== DOCUMENTOS HUÉRFANOS (si existen) ==========
SELECT '\n=== DOCUMENTOS HUÉRFANOS (deberían ser 0) ===' as '';

SELECT 
    id,
    titulo,
    seccion_id,
    subseccion_id,
    tipo_documento_id
FROM documentos
WHERE seccion_id IS NULL 
   OR subseccion_id IS NULL 
   OR tipo_documento_id IS NULL
LIMIT 10;

-- ========== ESTRUCTURA ACTUAL VS BSF OFICIAL ==========
SELECT '\n=== COMPARACIÓN: ACTUAL vs BSF OFICIAL ===' as '';

SELECT 
    'Secciones' as 'Categoría',
    (SELECT COUNT(*) FROM secciones) as 'Actual',
    9 as 'BSF Oficial',
    CASE 
        WHEN (SELECT COUNT(*) FROM secciones) < 9 THEN 'Faltan secciones ⚠️'
        WHEN (SELECT COUNT(*) FROM secciones) = 9 THEN 'Correcto ✓'
        ELSE 'Sobran secciones ⚠️'
    END as 'Estado'
UNION ALL
SELECT 
    'Tipos de Documento',
    (SELECT COUNT(*) FROM tipos_documento),
    35,
    CASE 
        WHEN (SELECT COUNT(*) FROM tipos_documento) < 35 THEN 'Faltan tipos ⚠️'
        WHEN (SELECT COUNT(*) FROM tipos_documento) >= 35 THEN 'Suficientes ✓'
        ELSE 'Error'
    END;

-- FIN DEL ANÁLISIS
SELECT '\n=== ANÁLISIS COMPLETADO ===' as '';
