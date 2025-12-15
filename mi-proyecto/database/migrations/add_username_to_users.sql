-- ============================================================================
-- MIGRACIÓN: Agregar campo username a tabla users
-- ============================================================================
-- Propósito: Agregar columna username UNIQUE para identificación de usuarios
-- Autor: Sistema RBAC
-- Fecha: 2025-11-23
-- Base de datos: bsf_core
-- ============================================================================

USE bsf_core;

-- 1. Verificar si la columna ya existe (seguridad)
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'bsf_core'
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'username'
);

-- 2. Agregar columna username si no existe
SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER email',
    'SELECT "La columna username ya existe" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Crear índice único para username
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'bsf_core'
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_username_unique'
);

SET @sql = IF(
    @index_exists = 0 AND @column_exists = 0,
    'CREATE UNIQUE INDEX idx_users_username_unique ON users(username)',
    'SELECT "El índice idx_users_username_unique ya existe o la columna no fue creada" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Generar usernames para usuarios existentes que no lo tengan
-- Formato: primera letra del nombre + apellido (si hay) + id
-- Ejemplo: Juan Pérez (id=5) → jperez5

UPDATE users
SET username = CONCAT(
    LOWER(SUBSTRING(name, 1, 1)),
    LOWER(
        COALESCE(
            SUBSTRING_INDEX(name, ' ', -1),
            SUBSTRING(name, 2)
        )
    ),
    id
)
WHERE username IS NULL OR username = '';

-- 5. Hacer la columna NOT NULL después de poblarla
SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE users MODIFY COLUMN username VARCHAR(100) NOT NULL',
    'SELECT "Username no modificado porque la columna ya existía" AS mensaje'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================

-- Mostrar usuarios con sus usernames generados
SELECT 
    id,
    name,
    email,
    username,
    role,
    status
FROM users
ORDER BY id;

-- Verificar estructura final
SHOW COLUMNS FROM users LIKE 'username';

SELECT 'Migración completada exitosamente' AS resultado;
