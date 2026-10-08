-- Fechamento de venda no caixa (cadeado ADM) — só se AINDA NÃO existir em produção
-- Compatível com migration: 2026_04_09_100000_venda_workflow_fechamento_caixa.php

ALTER TABLE `vendas`
  ADD COLUMN `fechada_caixa` tinyint(1) NOT NULL DEFAULT 0 AFTER `status_pagamento`,
  ADD COLUMN `fechada_em` timestamp NULL DEFAULT NULL AFTER `fechada_caixa`,
  ADD COLUMN `fechada_por_usuario_id` int unsigned DEFAULT NULL AFTER `fechada_em`;

ALTER TABLE `vendas`
  ADD CONSTRAINT `vendas_fechada_por_usuario_id_foreign`
  FOREIGN KEY (`fechada_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

UPDATE `vendas` SET `status_pedido` = 'aguardando_confirmacao' WHERE `status_pedido` = 'em_elaboracao';
UPDATE `vendas` SET `status_pedido` = 'confirmado' WHERE `status_pedido` = 'finalizada';
