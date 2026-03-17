-- Agrega columna JSON para almacenar títulos personalizados de secciones
-- tanto en Fase 2 (proforma) como en los documentos generados (Fase 3)
-- Ejecutar en el servidor de producción:
--   mysql -u usuario -p brixs_sistema < alter_secciones_titulos.sql
ALTER TABLE `procesos`
  ADD COLUMN `secciones_titulos` JSON DEFAULT NULL
    COMMENT 'Títulos personalizados de secciones: {"especificaciones_tecnicas":"Mi título", ...}';
