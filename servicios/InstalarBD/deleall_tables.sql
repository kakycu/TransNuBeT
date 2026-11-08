-- ELIMINAR TODAS LAS BASES DE DATOS MENOS LAS DEL SISTEMA
-- PROCEDIMIENTO (MUCHO CUIDADO)

-- Seleccionar una base de datos existente
USE mysql;

-- Eliminar el procedimiento si ya existe
DROP PROCEDURE IF EXISTS eliminar_bases_excepto_sistema;

-- Crear el procedimiento
DELIMITER $$

CREATE PROCEDURE eliminar_bases_excepto_sistema()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE db_name VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT SCHEMA_NAME 
        FROM information_schema.SCHEMATA 
        WHERE SCHEMA_NAME NOT IN ('information_schema', 'mysql', 'performance_schema', 'phpmyadmin', 'sys');
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO db_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Ejecutar el DROP DATABASE
        SET @sql = CONCAT('DROP DATABASE IF EXISTS `', db_name, '`;');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

-- Ejecutar el procedimiento
CALL eliminar_bases_excepto_sistema();

-- Limpiar (eliminar el procedimiento después de usarlo)
DROP PROCEDURE IF EXISTS eliminar_bases_excepto_sistema;