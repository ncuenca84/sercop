-- Ampliar columnas del proceso para soportar imágenes base64 en campos de texto
-- Ejecutar en el servidor de producción: mysql -u usuario -p brixs_sistema < alter_vigencia_oferta.sql
ALTER TABLE `procesos`
  MODIFY COLUMN `vigencia_oferta` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `forma_pago` LONGTEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL;
