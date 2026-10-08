-- Versão do pedido / PDF / ficha de separação
-- Compatível com migration: 2026_06_09_120000_add_versao_pedido_to_vendas_table.php
-- Rode DEPOIS dos scripts 01–06 (vendas já com status_pedido e status_pagamento).

ALTER TABLE `vendas`
  ADD COLUMN `versao_pedido` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status_pagamento`,
  ADD COLUMN `versao_ficha_impressa` INT UNSIGNED NULL AFTER `versao_pedido`,
  ADD COLUMN `versao_pedido_pdf` INT UNSIGNED NULL AFTER `versao_ficha_impressa`,
  ADD COLUMN `ficha_impressa_em` TIMESTAMP NULL DEFAULT NULL AFTER `versao_pedido_pdf`,
  ADD COLUMN `pedido_pdf_em` TIMESTAMP NULL DEFAULT NULL AFTER `ficha_impressa_em`;

-- Se a coluna fechada_caixa já existir (migration 2026_04_09), use em vez do ALTER acima:
-- ALTER TABLE `vendas`
--   ADD COLUMN `versao_pedido` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `fechada_por_usuario_id`,
--   ADD COLUMN `versao_ficha_impressa` INT UNSIGNED NULL AFTER `versao_pedido`,
--   ADD COLUMN `versao_pedido_pdf` INT UNSIGNED NULL AFTER `versao_ficha_impressa`,
--   ADD COLUMN `ficha_impressa_em` TIMESTAMP NULL DEFAULT NULL AFTER `versao_pedido_pdf`,
--   ADD COLUMN `pedido_pdf_em` TIMESTAMP NULL DEFAULT NULL AFTER `ficha_impressa_em`;
