-- Migración: agrega categorías faltantes al ENUM categoria de documentos_proceso
-- Necesario para: Informe de Necesidad e Imágenes del Proceso en Expediente Digital

ALTER TABLE `documentos_proceso`
  MODIFY COLUMN `categoria` enum(
    'informe_necesidad',
    'tdr',
    'orden_compra',
    'proforma',
    'doc_proveedor',
    'garantia',
    'informe_tecnico',
    'acta_entrega',
    'factura',
    'solicitud_pago',
    'comunicacion',
    'imagenes_f2',
    'otro'
  ) COLLATE utf8mb4_unicode_ci DEFAULT 'otro';
