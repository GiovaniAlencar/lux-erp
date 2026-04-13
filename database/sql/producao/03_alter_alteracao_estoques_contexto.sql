-- Contexto da movimentação de estoque (origem, pedido, saldo antes/depois)
-- Compatível com migration: 2026_04_08_180900_add_contexto_to_alteracao_estoques_table.php

ALTER TABLE `alteracao_estoques`
  ADD COLUMN `acao` VARCHAR(60) NULL DEFAULT NULL AFTER `tipo`,
  ADD COLUMN `origem` VARCHAR(60) NULL DEFAULT NULL AFTER `acao`,
  ADD COLUMN `origem_id` INT NULL DEFAULT NULL AFTER `origem`,
  ADD COLUMN `pedido_id` INT UNSIGNED NULL DEFAULT NULL AFTER `origem_id`,
  ADD COLUMN `item_pedido_id` INT UNSIGNED NULL DEFAULT NULL AFTER `pedido_id`,
  ADD COLUMN `estoque_anterior` DECIMAL(10,3) NULL DEFAULT NULL AFTER `observacao`,
  ADD COLUMN `estoque_novo` DECIMAL(10,3) NULL DEFAULT NULL AFTER `estoque_anterior`;
