-- =========================================================
-- Migración: Crear tabla de auditoría de gestión de usuarios
-- Fecha: 2025-01-22
-- Descripción: Registra TODOS los cambios realizados por superadmins
--              en la gestión de usuarios del sistema
-- =========================================================

USE bsf_core;

-- Crear tabla de auditoría de gestión de usuarios
CREATE TABLE IF NOT EXISTS user_management_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Quién realizó la acción
    admin_user_id BIGINT UNSIGNED NOT NULL COMMENT 'ID del superadmin que realizó la acción',
    admin_username VARCHAR(100) COMMENT 'Username del admin (desnormalizado para auditoría)',
    
    -- Sobre quién se realizó la acción
    target_user_id BIGINT UNSIGNED COMMENT 'ID del usuario afectado (NULL si es creación)',
    target_username VARCHAR(100) COMMENT 'Username del usuario afectado',
    
    -- Qué acción se realizó
    action VARCHAR(50) NOT NULL COMMENT 'Tipo de acción: create, update_role, update_status, delete, restore, reuse_username, resend_invitation',
    
    -- Detalles del cambio
    old_values JSON COMMENT 'Valores anteriores en formato JSON',
    new_values JSON COMMENT 'Valores nuevos en formato JSON',
    reason TEXT COMMENT 'Motivo del cambio (especialmente para dar de baja)',
    
    -- Metadata
    ip_address VARCHAR(45) COMMENT 'IP desde donde se realizó la acción',
    user_agent TEXT COMMENT 'User agent del navegador',
    
    -- Timestamp
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para consultas rápidas
    INDEX idx_admin_user (admin_user_id, created_at DESC),
    INDEX idx_target_user (target_user_id, created_at DESC),
    INDEX idx_action (action, created_at DESC),
    INDEX idx_created_at (created_at DESC)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoría completa de todas las acciones de gestión de usuarios realizadas por superadmins';

-- Verificar creación
SELECT 'Tabla user_management_audit creada correctamente' AS resultado;
DESCRIBE user_management_audit;
