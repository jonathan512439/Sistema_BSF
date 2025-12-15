-- SQL para agregar campos faltantes en documento_ubicacion
-- Ejecutar en base de datos: bsf_core

USE bsf_core;

-- ====================================================================
-- OPCIÓN 1: Agregar campo 'motivo' (falta en la tabla actual)
-- ====================================================================

-- Verificar si existe el campo 'motivo'
SET @column_exists_motivo = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'bsf_core' 
    AND TABLE_NAME = 'documento_ubicacion' 
    AND COLUMN_NAME = 'motivo'
);

SET @sql_add_motivo = IF(@column_exists_motivo = 0,
    'ALTER TABLE documento_ubicacion ADD COLUMN `motivo` VARCHAR(255) NULL DEFAULT NULL COMMENT ''Razón del movimiento'' AFTER `ubicacion_fisica_id`',
    'SELECT "Campo motivo ya existe" AS resultado'
);

PREPARE stmt FROM @sql_add_motivo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================================
-- VERIFICACIÓN FINAL
-- ====================================================================

-- Mostrar estructura actual de la tabla
DESCRIBE documento_ubicacion;

-- Verificar índices
SHOW INDEX FROM documento_ubicacion;

SELECT '✅ Estructura de documento_ubicacion actualizada correctamente' AS resultado;

-- ====================================================================
-- NOTA IMPORTANTE
-- ====================================================================
-- La tabla usa:
--   - fecha_asignacion (cuando se asigna a la ubicación)
--   - fecha_retiro (cuando se retira de la ubicación)
-- El código PHP debe usar estos nombres, NO 'desde' y 'hasta'
