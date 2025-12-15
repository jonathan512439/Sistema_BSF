-- =====================================================
-- Migración: Agregar campo is_confidential a documentos
-- Fecha: 2025-01-22
-- Descripción: Permite marcar documentos como confidenciales
--              Solo archivistas pueden ver documentos confidenciales
--              Lectores solo ven documentos NO confidenciales
-- =====================================================

USE bsf_core;

-- Agregar columna is_confidential con valor por defecto TRUE
ALTER TABLE documentos 
ADD COLUMN IF NOT EXISTS is_confidential BOOLEAN NOT NULL DEFAULT TRUE 
COMMENT 'Documento confidencial (solo archivistas pueden ver)' 
AFTER estado;

-- Crear índice compuesto para mejorar consultas de lectores
CREATE INDEX IF NOT EXISTS idx_documentos_confidential_estado 
ON documentos(is_confidential, estado);

-- Verificar resultado
SELECT 'Columna is_confidential agregada correctamente' AS resultado;
DESCRIBE documentos;
