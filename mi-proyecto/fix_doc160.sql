-- Fix para documento #160 - Actualizar es_version_actual
UPDATE documento_versiones 
SET es_version_actual = 0 
WHERE documento_id = 160;

UPDATE documento_versiones 
SET es_version_actual = 1 
WHERE documento_id = 160 AND version_numero = 1;

-- Verificar
SELECT id, documento_id, version_numero, es_version_actual 
FROM documento_versiones 
WHERE documento_id = 160;
