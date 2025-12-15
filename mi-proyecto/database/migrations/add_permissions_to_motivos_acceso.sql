-- =====================================================
-- Script: Agregar columnas de permisos a motivos_acceso
-- Base de datos: bsf_core
-- Descripción: Agrega columnas can_view, can_print, can_download
--              y actualiza los motivos existentes con sus permisos
-- =====================================================

USE bsf_core;

-- Paso 1: Agregar columnas de permisos si no existen
ALTER TABLE motivos_acceso 
ADD COLUMN IF NOT EXISTS can_view BOOLEAN DEFAULT 1 COMMENT 'Permite visualizar el documento',
ADD COLUMN IF NOT EXISTS can_print BOOLEAN DEFAULT 0 COMMENT 'Permite imprimir el documento',
ADD COLUMN IF NOT EXISTS can_download BOOLEAN DEFAULT 0 COMMENT 'Permite descargar el documento';

-- Paso 2: Actualizar permisos para los motivos existentes
-- Nota: Ajusta los IDs según los IDs reales en tu tabla

-- Motivo: "Visualización autorizada" - Solo ver
UPDATE motivos_acceso 
SET can_view = 1, can_print = 0, can_download = 0
WHERE descripcion = 'Visualización autorizada';

-- Motivo: "Impresión por orden" - Ver + Imprimir
UPDATE motivos_acceso 
SET can_view = 1, can_print = 1, can_download = 0
WHERE descripcion = 'Impresión por orden';

-- Motivo: "Copia para adjuntar a informe" - Ver + Descargar
UPDATE motivos_acceso 
SET can_view = 1, can_print = 0, can_download = 1
WHERE descripcion = 'Copia para adjuntar a informe';

-- Paso 3: Verificar los cambios
SELECT id, descripcion, can_view, can_print, can_download, activo
FROM motivos_acceso
ORDER BY id;

-- =====================================================
-- Notas de implementación:
-- 1. Ejecuta este script directamente en MySQL/phpMyAdmin
-- 2. Verifica que los nombres de los motivos coincidan exactamente
-- 3. Si los nombres no coinciden, ajusta los WHERE descripcion
-- 4. También puedes actualizar por ID si conoces los IDs exactos
-- =====================================================
