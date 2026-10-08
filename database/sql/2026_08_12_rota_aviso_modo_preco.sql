-- Campos extras: rota (embalagem, aviso, confirmado) + venda (modo preço, aviso)
-- Banco: luxerp

ALTER TABLE `rota_entrega_itens`
  ADD COLUMN IF NOT EXISTS `embalagem_tipo` varchar(20) NULL AFTER `complemento`,
  ADD COLUMN IF NOT EXISTS `embalagem_qtd` smallint unsigned NULL AFTER `embalagem_tipo`,
  ADD COLUMN IF NOT EXISTS `aviso_entrega` varchar(255) NULL AFTER `observacao_prioridade`,
  ADD COLUMN IF NOT EXISTS `confirmado_saida` tinyint(1) NOT NULL DEFAULT 0 AFTER `aviso_entrega`;

ALTER TABLE `vendas`
  ADD COLUMN IF NOT EXISTS `modo_preco_arabes` varchar(20) NOT NULL DEFAULT 'auto' AFTER `status_pagamento`,
  ADD COLUMN IF NOT EXISTS `modo_preco_miniaturas` varchar(20) NOT NULL DEFAULT 'auto' AFTER `modo_preco_arabes`,
  ADD COLUMN IF NOT EXISTS `aviso_entrega` varchar(255) NULL AFTER `observacao`;
