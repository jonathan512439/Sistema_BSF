-- Fix para versiones con creado_por NULL
-- Esto establece usuario 1 por defecto para versiones sin creado_por

UPDATE documento_versiones 
SET creado_por = 1 
WHERE creado_por IS NULL OR creado_por = 0;

-- Verificar
SELECT 
    COUNT(*) as total_versiones,
    SUM(CASE WHEN creado_por IS NULL THEN 1 ELSE 0 END) as con_null,
    SUM(CASE WHEN creado_por = 1 THEN 1 ELSE 0 END) as con_usuario_1
FROM documento_versiones;
