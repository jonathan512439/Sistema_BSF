-- =====================================================
-- MIGRACIÓN SEGURA A ESTRUCTURA BSF OFICIAL
-- Base de Datos: bsf_core
-- Fecha: 2025-12-15
-- =====================================================
-- IMPORTANTE: Leer TODO antes de ejecutar
-- Ejecutar sección por sección, NO todo junto
-- =====================================================

USE bsf_core;

-- =====================================================
-- PASO 1: BACKUP COMPLETO
-- =====================================================

-- Crear tablas de backup
DROP TABLE IF EXISTS secciones_backup;
CREATE TABLE secciones_backup LIKE secciones;
INSERT INTO secciones_backup SELECT * FROM secciones;

DROP TABLE IF EXISTS subsecciones_backup;
CREATE TABLE subsecciones_backup LIKE subsecciones;
INSERT INTO subsecciones_backup SELECT * FROM subsecciones;

DROP TABLE IF EXISTS tipos_documento_backup;
CREATE TABLE tipos_documento_backup LIKE tipos_documento;
INSERT INTO tipos_documento_backup SELECT * FROM tipos_documento;

DROP TABLE IF EXISTS documentos_backup;
CREATE TABLE documentos_backup LIKE documentos;
INSERT INTO documentos_backup SELECT * FROM documentos;

-- Verificar backup
SELECT 
    'BACKUP COMPLETADO' as mensaje,
    (SELECT COUNT(*) FROM secciones_backup) as secciones_backup,
    (SELECT COUNT(*) FROM subsecciones_backup) as subsecciones_backup,
    (SELECT COUNT(*) FROM tipos_documento_backup) as tipos_backup,
    (SELECT COUNT(*) FROM documentos_backup) as documentos_backup;

-- =====================================================
-- PASO 2: LIMPIAR DOCUMENTOS DE PRUEBA HUÉRFANOS
-- =====================================================

-- Estos son documentos de prueba sin sección/subsección/tipo
-- Puedes eliminarlos o asignarles valores
-- OPCIÓN A: ELIMINAR (recomendado para pruebas)
DELETE FROM documentos 
WHERE id IN (1, 3, 5, 106, 107, 108, 109, 112, 113, 114, 115, 116, 117, 119, 130);

-- OPCIÓN B: ASIGNAR valores por defecto (descomenta si prefieres mantenerlos)
-- UPDATE documentos 
-- SET seccion_id = 1, subseccion_id = 1, tipo_documento_id = 1
-- WHERE id IN (1, 3, 5, 106, 107, 108, 109, 112, 113, 114, 115, 116, 117, 119, 130);

-- Verificar que no queden huérfanos
SELECT COUNT(*) as huerfanos_restantes
FROM documentos
WHERE seccion_id IS NULL OR subseccion_id IS NULL OR tipo_documento_id IS NULL;

-- =====================================================
-- PASO 3: CREAR SECCIONES BSF OFICIALES
-- =====================================================

-- Deshabilitar temporalmente foreign keys
SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar secciones actuales (los docs ya tienen backup)
TRUNCATE TABLE secciones;

-- Insertar las 9 secciones oficiales BSF
INSERT INTO secciones (id, nombre, descripcion) VALUES
(1, 'Comando y Dirección', 'Documentos de la comandancia y dirección del batallón'),
(2, 'Administrativa', 'Documentos administrativos, logísticos y financieros'),
(3, 'Personal', 'Documentos de recursos humanos y personal'),
(4, 'Inteligencia', 'Documentos de inteligencia y análisis'),
(5, 'Planeamiento y Operaciones', 'Documentos de planeamiento táctico y operaciones'),
(6, 'Supervisión y Control', 'Documentos de supervisión y control operativo'),
(7, 'Jurídica', 'Documentos legales, disciplinarios y jurídicos'),
(8, 'Bienestar Social', 'Documentos de bienestar, psicología y trabajo social'),
(9, 'Operaciones de Seguridad', 'Documentos de operaciones de seguridad específicas');

-- Verificar
SELECT * FROM secciones ORDER BY id;

-- =====================================================
-- PASO 4: ACTUALIZAR DOCUMENTOS CON MAPEO
-- =====================================================

-- IMPORTANTE: Usar IDs de secciones ANTIGUAS (del backup)
-- Mapeo basado en tu estructura actual:

-- Personal (id=1) → Personal (id=3) BSF
UPDATE documentos SET seccion_id = 3 WHERE seccion_id = 1;

-- Disciplinario (id=2) → Jurídica (id=7) BSF
UPDATE documentos SET seccion_id = 7 WHERE seccion_id = 2;

-- Operativo (id=3) → Planeamiento y Operaciones (id=5) BSF
UPDATE documentos SET seccion_id = 5 WHERE seccion_id = 3;

-- Logística (id=4) → Administrativa (id=2) BSF
UPDATE documentos SET seccion_id = 2 WHERE seccion_id = 4;

-- Armamento (id=5) → Administrativa (id=2) BSF
UPDATE documentos SET seccion_id = 2 WHERE seccion_id = 5;

-- Vehículos (id=6) → Administrativa (id=2) BSF
UPDATE documentos SET seccion_id = 2 WHERE seccion_id = 6;

-- Correspondencia (id=7) → Comando y Dirección (id=1) BSF
UPDATE documentos SET seccion_id = 1 WHERE seccion_id = 7;

-- Asuntos Internos (id=8) → Supervisión y Control (id=6) BSF
UPDATE documentos SET seccion_id = 6 WHERE seccion_id = 8;

-- Recursos Humanos (id=9) → Personal (id=3) BSF
UPDATE documentos SET seccion_id = 3 WHERE seccion_id = 9;

-- Finanzas (id=10) → Administrativa (id=2) BSF
UPDATE documentos SET seccion_id = 2 WHERE seccion_id = 10;

-- Verificar mapeo
SELECT 
    s.nombre as seccion,
    COUNT(d.id) as documentos
FROM documentos d
JOIN secciones s ON d.seccion_id = s.id
GROUP BY s.id
ORDER BY s.id;

-- =====================================================
-- PASO 5: RECREAR SUBSECCIONES BSF OFICIALES
-- =====================================================

-- Limpiar subsecciones actuales
TRUNCATE TABLE subsecciones;

-- 1. Comando y Dirección (seccion_id = 1)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Comandancia', 'Documentos de la comandancia', 1),
('Subcomandancia', 'Documentos de la subcomandancia', 1),
('Secretaría General', 'Documentos de secretaría', 1),
('Estafeta', 'Correspondencia y mensajería', 1),
('Relaciones Públicas', 'Comunicación institucional', 1);

-- 2. Administrativa (seccion_id = 2)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Activos Fijos', 'Gestión de activos fijos', 2),
('Furrielato, Equipo y Armamento', 'Gestión de equipo militar', 2),
('Logística', 'Gestión logística', 2),
('Archivo Administrativo', 'Archivo general administrativo', 2),
('Finanzas', 'Gestión financiera y presupuesto', 2);

-- 3. Personal (seccion_id = 3)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Movimiento de Personal', 'Altas, bajas, traslados', 3),
('Archivo y Kardex', 'Expedientes de personal', 3),
('Capacitación y Evaluación', 'Cursos y evaluaciones', 3);

-- 4. Inteligencia (seccion_id = 4)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Recopilación y Procesamiento', 'Datos de inteligencia', 4),
('Análisis y Evaluación', 'Informes de análisis', 4),
('Archivo de Inteligencia', 'Archivo clasificado', 4);

-- 5. Planeamiento y Operaciones (seccion_id = 5)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Planeamiento', 'Planes estratégicos', 5),
('Operaciones', 'Ejecución operativa', 5),
('Roles de Servicio', 'Asignaciones de servicio', 5);

-- 6. Supervisión y Control (seccion_id = 6)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Supervisión Operativa', 'Control de operaciones', 6),
('Radio Operaciones', 'Comunicaciones', 6),
('Control de Servicios', 'Verificación de servicios', 6);

-- 7. Jurídica (seccion_id = 7)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Asesoría Jurídica', 'Consultas legales', 7),
('Procesos Disciplinarios', 'Sanciones y procesos', 7);

-- 8. Bienestar Social (seccion_id = 8)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Psicología', 'Evaluaciones psicológicas', 8),
('Trabajo Social', 'Asistencia social', 8),
('Salud y Educación', 'Programas de salud y educación', 8);

-- 9. Operaciones de Seguridad (seccion_id = 9)
INSERT INTO subsecciones (nombre, descripcion, seccion_id) VALUES
('Bancaria y Financiera', 'Seguridad bancaria', 9),
('Comercial', 'Seguridad comercial', 9),
('Industrial e Instituciones Estratégicas', 'Seguridad industrial', 9),
('Transporte de Valores', 'Custodia de valores', 9),
('Social, Educativa y Salud', 'Seguridad social', 9),
('Domiciliaria y Seguridad VIP', 'Protección personal', 9);

-- Verificar subsecciones creadas
SELECT 
    s.nombre as seccion,
    sub.nombre as subseccion
FROM subsecciones sub
JOIN secciones s ON sub.seccion_id = s.id
ORDER BY sub.seccion_id, sub.id;

-- =====================================================
-- PASO 6: MAPEAR SUBSECCIONES EN DOCUMENTOS
-- =====================================================

-- Mapeo manual según tus datos actuales
-- Necesitamos revisar documento por documento

-- Para documentos de Personal (ahora con seccion_id=3)
-- Subsecciones viejas: Legajos(1), Ascensos(2), Evaluaciones(3), Licencias(4)
-- Subsecciones nuevas: Archivo y Kardex(11), Movimiento(10), Capacitación(12)

UPDATE documentos 
SET subseccion_id = 11 -- Archivo y Kardex
WHERE subseccion_id IN (1, 2); -- Legajos y Ascensos antiguos

UPDATE documentos 
SET subseccion_id = 12 -- Capacitación y Evaluación
WHERE subseccion_id = 3; -- Evaluaciones antiguas

-- Para documentos de Jurídica (antes Disciplinario, seccion_id=7)
UPDATE documentos 
SET subseccion_id = 20 -- Procesos Disciplinarios
WHERE subseccion_id = 6; -- Sumarios antiguos

-- Para documentos Operativos (ahora Planeamiento y Operaciones, seccion_id=5)
UPDATE documentos 
SET subseccion_id = 14 -- Operaciones
WHERE subseccion_id IN (8, 9); -- Operativos Planificados y Partes Diarios

-- Para documentos Administrativos (seccion_id=2)
UPDATE documentos 
SET subseccion_id = 8 -- Logística
WHERE subseccion_id = 11; -- Inventarios antiguos

UPDATE documentos 
SET subseccion_id = 7 -- Furrielato, Equipo y Armamento
WHERE subseccion_id = 14; -- Entrega-Recepción Armamento antiguo

UPDATE documentos 
SET subseccion_id = 9 -- Finanzas
WHERE seccion_id = 2 AND subseccion_id IN (24, 25); -- RR.HH. antiguo

-- Para Correspondencia (ahora Comando y Dirección, seccion_id=1)
UPDATE documentos 
SET subseccion_id = 3 -- Secretaría General
WHERE subseccion_id = 21; -- Memorándums antiguos

-- =====================================================
-- PASO 7: COMPLETAR TIPOS DE DOCUMENTO BSF
-- =====================================================

-- Limpiar tipos actuales (manteniendo los que tienen docs)
-- NO borrar, solo agregar los faltantes

-- Agregar tipos faltantes
INSERT INTO tipos_documento (nombre, descripcion, categoria) VALUES
-- Documentos de Dirección (faltan 5)
('Resolución', 'Resolución administrativa', 'Dirección'),
('Orden de Servicio', 'Orden de servicio operativo', 'Dirección'),
('Orden de Operaciones', 'Orden de operaciones tácticas', 'Dirección'),
('Directiva', 'Directiva institucional', 'Dirección'),
('Disposición', 'Disposición administrativa', 'Dirección'),

-- Documentos Administrativos (ya tienes Memorándum, Circular, Oficio)
('Nota Interna', 'Nota administrativa interna', 'Administrativo'),
('Correspondencia', 'Correspondencia general', 'Administrativo'),

-- Documentos de Informe (faltan varios)
('Informe Administrativo', 'Informe de gestión administrativa', 'Informe'),
('Informe Operativo', 'Informe de operaciones', 'Informe'),
('Informe Reservado', 'Informe clasificado', 'Informe'),
('Informe de Inteligencia', 'Informe de inteligencia', 'Informe'),
('Informe de Supervisión', 'Informe de control', 'Informe'),
('Informe Psicológico', 'Evaluación psicológica', 'Informe'),
('Informe Jurídico', 'Informe legal', 'Informe'),

-- Documentos Operativos (ya tienes Parte Operativo)
('Parte Diario', 'Reporte diario', 'Operativo'),
('Reporte de Servicio', 'Reporte de servicio', 'Operativo'),
('Registro de Novedades', 'Registro de novedades', 'Operativo'),
('Registro de Incidentes', 'Registro de incidentes', 'Operativo'),

-- Documentos de Planeamiento
('Plan', 'Plan general', 'Planeamiento'),
('Plan de Seguridad', 'Plan de seguridad específico', 'Planeamiento'),
('Plan de Contingencia', 'Plan de emergencia', 'Planeamiento'),
('Cronograma', 'Cronograma de actividades', 'Planeamiento'),
('Rol de Servicio', 'Asignación de roles', 'Planeamiento'),

-- Documentos Legales (ya tienes Acta, Dictamen)
('Resolución Administrativa', 'Resolución de sanción', 'Legal'),
('Documento Probatorio', 'Evidencia documental', 'Legal'),
('Descargo', 'Descargo disciplinario', 'Legal'),
('Notificación', 'Notificación oficial', 'Legal'),

-- Documentos de Personal (ya tienes Expediente, Legajo)
('Certificado', 'Certificado oficial', 'Personal'),
('Evaluación', 'Evaluación de desempeño', 'Personal'),
('Kardex', 'Kardex de personal', 'Personal'),
('Lista de Personal', 'Nómina de personal', 'Personal'),

-- Documentos Logísticos
('Inventario', 'Inventario de bienes', 'Logístico'),
('Acta de Entrega-Recepción', 'Acta de transferencia', 'Logístico'),
('Registro de Activos', 'Registro de activos fijos', 'Logístico'),
('Registro de Armamento', 'Registro de armas', 'Logístico'),
('Informe de Mantenimiento', 'Mantenimiento de equipo', 'Logístico');

-- Verificar tipos
SELECT COUNT(*) as total_tipos FROM tipos_documento;
SELECT * FROM tipos_documento ORDER BY categoria, nombre;

-- Reactivar foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- PASO 8: VERIFICACIÓN FINAL
-- =====================================================

-- 1. Verificar que hay 9 secciones
SELECT COUNT(*) as secciones FROM secciones;
-- Debe ser: 9

-- 2. Verificar distribución de documentos
SELECT 
    s.nombre as seccion,
    COUNT(d.id) as documentos
FROM secciones s
LEFT JOIN documentos d ON s.id = d.seccion_id
GROUP BY s.id
ORDER BY s.id;

-- 3. Verificar que NO hay documentos huérfanos
SELECT 
    COUNT(*) as total_docs,
    SUM(CASE WHEN seccion_id IS NULL THEN 1 ELSE 0 END) as sin_seccion,
    SUM(CASE WHEN subseccion_id IS NULL THEN 1 ELSE 0 END) as sin_subseccion,
    SUM(CASE WHEN tipo_documento_id IS NULL THEN 1 ELSE 0 END) as sin_tipo
FROM documentos;
-- Todos deben ser 0 excepto total_docs

-- 4. Verificar integridad referencial
SELECT 
    d.id,
    d.titulo,
    s.nombre as seccion,
    sub.nombre as subseccion,
    t.nombre as tipo
FROM documentos d
JOIN secciones s ON d.seccion_id = s.id
JOIN subsecciones sub ON d.subseccion_id = sub.id
JOIN tipos_documento t ON d.tipo_documento_id = t.id
LIMIT 10;

--=====================================================
-- FIN DE LA MIGRACIÓN
-- =====================================================

SELECT '✅ MIGRACIÓN COMPLETADA' as mensaje;
