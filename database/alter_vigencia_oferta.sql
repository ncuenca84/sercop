-- Ampliar columna vigencia_oferta de varchar(50) a TEXT
-- Ejecutar en el servidor de producción: mysql -u usuario -p brixs_sistema < alter_vigencia_oferta.sql
ALTER TABLE `procesos`
  MODIFY COLUMN `vigencia_oferta` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL;
