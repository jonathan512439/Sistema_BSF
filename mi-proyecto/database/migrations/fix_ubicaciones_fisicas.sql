-- Script SQL para corregir estructura de ubicaciones físicas
-- Ejecutar en base de datos: bsf_core

USE bsf_core;

-- ====================================================================
-- PARTE 1: Verificar y corregir tabla ubicaciones_fisicas
-- ====================================================================

-- Asegurarse de que la tabla ubicaciones_fisicas existe
-- Si no existe, crearla con la estructura correcta
CREATE TABLE IF NOT EXISTS `ubicaciones_fisicas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL COMMENT 'Nombre descriptivo de la ubicación',
  `codigo` varchar(100) DEFAULT NULL COMMENT 'Código único de la ubicación (ej: EST-A-03)',
  `tipo` enum('estante','caja','archivo','deposito','otro') DEFAULT 'estante',
  `descripcion` text DEFAULT NULL,
  `capacidad_max` int(11) DEFAULT NULL COMMENT 'Capacidad máxima de documentos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ubicaciones_fisicas_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- PARTE 2: Agregar ubicaciones de ejemplo si la tabla está vacía
-- ====================================================================

INSERT IGNORE INTO `ubicaciones_fisicas` (`id`, `nombre`, `codigo`, `tipo`, `descripcion`, `capacidad_max`, `activo`, `created_at`, `updated_at`)
VALUES
(1, 'Estante Principal A', 'EST-A', 'estante', 'Estante principal en archivo central', 100, 1, NOW(), NOW()),
(2, 'Caja Archivo 001', 'CAJA-001', 'caja', 'Caja de archivo para documentos históricos', 50, 1, NOW(), NOW()),
(3, 'Depósito General', 'DEP-GEN', 'deposito', 'Depósito general de documentos', 500, 1, NOW(), NOW());

-- ====================================================================
-- PARTE 3: Verificar estructura tabla documento_ubicacion
-- ====================================================================

-- Esta tabla debe tener el campo ubicacion_fisica_id (NO ubicacion_id)
-- Ya está correcta según el dump SQL, pero verificamos por si acaso

-- Mostrar estructura actual
SHOW COLUMNS FROM documento_ubicacion LIKE 'ubicacion%';

-- ====================================================================
-- PARTE 4: Script de migración si existe data con nombre incorrecto
-- ====================================================================

-- Solo ejecutar si tienes datos con el campo mal nombrado
-- (Este bloque es preventivo, probablemente no sea necesario)

-- Verificar si existe columna ubicacion_id (nombre incorrecto)
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'bsf_core' 
    AND TABLE_NAME = 'documento_ubicacion' 
    AND COLUMN_NAME = 'ubicacion_id'
);

-- Si existe ubicacion_id, renombrarla a ubicacion_fisica_id
SET @sql_rename = IF(@column_exists > 0,
    'ALTER TABLE documento_ubicacion CHANGE COLUMN ubicacion_id ubicacion_fisica_id bigint(20) UNSIGNED NOT NULL',
    'SELECT "Columna ya está correcta" AS resultado'
);

PREPARE stmt FROM @sql_rename;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================================
-- PARTE 5: Verificar foreign keys
-- ====================================================================

-- Eliminar FK si existe (para recrearla correctamente)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'bsf_core'
    AND TABLE_NAME = 'documento_ubicacion'
    AND CONSTRAINT_NAME = 'documento_ubicacion_ubicacion_fisica_id_foreign'
);

SET @sql_drop_fk = IF(@fk_exists > 0,
    'ALTER TABLE documento_ubicacion DROP FOREIGN KEY documento_ubicacion_ubicacion_fisica_id_foreign',
    'SELECT "FK no existe" AS resultado'
);

PREPARE stmt FROM @sql_drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear FK correcta
ALTER TABLE `documento_ubicacion`
ADD CONSTRAINT `documento_ubicacion_ubicacion_fisica_id_foreign` 
FOREIGN KEY (`ubicacion_fisica_id`) 
REFERENCES `ubicaciones_fisicas` (`id`) 
ON DELETE RESTRICT 
ON UPDATE CASCADE;

-- ====================================================================
-- PARTE 6: Verificación final
-- ====================================================================

-- Mostrar estructura final de la tabla
DESCRIBE documento_ubicacion;

-- Mostrar ubicaciones disponibles
SELECT * FROM ubicaciones_fisicas WHERE activo = 1;

-- Contar documentos con ubicación asignada
SELECT COUNT(*) AS documentos_con_ubicacion FROM documento_ubicacion;

SELECT '✅ Script completado exitosamente' AS resultado;
