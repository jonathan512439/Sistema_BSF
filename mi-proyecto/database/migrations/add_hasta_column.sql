-- SQL para agregar el campo 'hasta' faltante en la tabla documento_ubicacion
-- Ejecutar en base de datos: bsf_core

USE bsf_core;

-- Agregar columna 'hasta' si no existe
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'bsf_core' 
    AND TABLE_NAME = 'documento_ubicacion' 
    AND COLUMN_NAME = 'hasta'
);

SET @sql_add = IF(@column_exists = 0,
    'ALTER TABLE documento_ubicacion ADD COLUMN `hasta` timestamp NULL DEFAULT NULL AFTER `desde`',
    'SELECT "Columna hasta ya existe" AS resultado'
);

PREPARE stmt FROM @sql_add;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar estructura final
DESCRIBE documento_ubicacion;

SELECT '✅ Campo hasta agregado correctamente' AS resultado;
