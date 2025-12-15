-- Script SQL para modificar el trigger que bloquea OCR en documentos sellados
-- Este trigger debe permitir actualización de confidence sin cambiar estado

USE bsf_core;

-- Eliminar el trigger antiguo
DROP TRIGGER IF EXISTS `documentos_no_degradar_estado`;

-- Crear el nuevo trigger mejorado
DELIMITER $$

CREATE TRIGGER `documentos_no_degradar_estado` BEFORE UPDATE ON `documentos` 
FOR EACH ROW 
BEGIN
    -- Permitir actualización de campos OCR sin cambiar estado
    -- Si solo se está actualizando ocr_confidence u ocr_version, permitir
    IF OLD.estado = 'custodio' AND NEW.estado <> OLD.estado THEN
        -- Verificar si es una actualización de OCR (solo confidence/version)
        IF NEW.ocr_confidence <> OLD.ocr_confidence OR NEW.ocr_version <> OLD.ocr_version THEN
            -- Permitir actualización de OCR manteniendo estado custodio
            SET NEW.estado = 'custodio';
        ELSE
            -- Bloquear cambio de estado desde custodio en otros casos
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede degradar estado desde custodio';
        END IF;
    END IF;
    
    -- Proteger el hash de custodia (siempre inmutable)
    IF OLD.custodia_hash IS NOT NULL AND NEW.custodia_hash <> OLD.custodia_hash THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='custodia_hash es inmutable';
    END IF;
END$$

DELIMITER ;

-- Verificar que se creó correctamente
SHOW TRIGGERS LIKE 'documentos';
