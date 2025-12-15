-- =====================================================
-- COMPLETAR SUBSECCIONES FALTANTES BSF
-- Base de Datos: bsf_core
-- =====================================================

USE bsf_core;

-- Verificar subsecciones actuales
SELECT COUNT(*) as total_subsecciones FROM subsecciones WHERE deleted_at IS NULL;

-- Agregar las subsecciones que faltan

-- 8. Bienestar Social (seccion_id = 8) - Faltan 2
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Trabajo Social', 'Asistencia social', 8),
('Salud y Educación', 'Programas de salud y educación', 8)
ON DUPLICATE KEY UPDATE nombre=nombre;

-- 9. Operaciones de Seguridad (seccion_id = 9) - Faltan todas
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Bancaria y Financiera', 'Seguridad bancaria', 9),
('Comercial', 'Seguridad comercial', 9),
('Industrial e Instituciones Estratégicas', 'Seguridad industrial', 9),
('Transporte de Valores', 'Custodia de valores', 9),
('Social, Educativa y Salud', 'Seguridad social', 9),
('Domiciliaria y Seguridad VIP', 'Protección personal', 9)
ON DUPLICATE KEY UPDATE nombre=nombre;

-- VERIFICACIÓN FINAL COMPLETA
SELECT '=== VERIFICACIÓN FINAL ESTRUCTURA BSF ===' as '';

-- 1. Secciones (debe ser 9)
SELECT COUNT(*) as total_secciones FROM secciones WHERE deleted_at IS NULL;

-- 2. Subsecciones por sección
SELECT 
    s.nombre as seccion,
    COUNT(sub.id) as subsecciones
FROM secciones s
LEFT JOIN subsecciones sub ON s.id = sub.seccion_id AND sub.deleted_at IS NULL
WHERE s.deleted_at IS NULL
GROUP BY s.id
ORDER BY s.id;

-- 3. Total subsecciones (debe ser ~35)
SELECT COUNT(*) as total_subsecciones FROM subsecciones WHERE deleted_at IS NULL;

-- 4. Tipos de documento (debe ser >= 35)
SELECT COUNT(*) as total_tipos FROM tipos_documento WHERE deleted_at IS NULL;

-- 5. Documentos sin huérfanos (debe ser 0, 0, 0)
SELECT 
    COUNT(*) as total_docs,
    SUM(CASE WHEN seccion_id IS NULL THEN 1 ELSE 0 END) as sin_seccion,
    SUM(CASE WHEN subseccion_id IS NULL THEN 1 ELSE 0 END) as sin_subseccion,
    SUM(CASE WHEN tipo_documento_id IS NULL THEN 1 ELSE 0 END) as sin_tipo
FROM documentos;

-- 6. Distribución de documentos por sección
SELECT 
    s.nombre as seccion,
    COUNT(d.id) as documentos
FROM secciones s
LEFT JOIN documentos d ON s.id = d.seccion_id
WHERE s.deleted_at IS NULL
GROUP BY s.id
ORDER BY s.id;

SELECT '✅ ESTRUCTURA BSF COMPLETA Y LISTA' as mensaje;
