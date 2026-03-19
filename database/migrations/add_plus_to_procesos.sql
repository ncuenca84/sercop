-- Migración: agrega columnas plus_activo y plus_texto a la tabla procesos
-- Ejecutar en producción para activar la sección "NUESTRO PLUS" en proformas.

ALTER TABLE `procesos`
  ADD COLUMN `plus_activo` tinyint(1) NOT NULL DEFAULT '1' AFTER `declaracion_activa`,
  ADD COLUMN `plus_texto`  longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `plus_activo`;
