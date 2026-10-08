ALTER TABLE `rota_entrega_itens`
  ADD COLUMN `embalagem_tipo` varchar(20) NULL AFTER `complemento`,
  ADD COLUMN `embalagem_qtd` smallint unsigned NULL AFTER `embalagem_tipo`,
  ADD COLUMN `aviso_entrega` varchar(255) NULL AFTER `observacao_prioridade`,
  ADD COLUMN `confirmado_saida` tinyint(1) NOT NULL DEFAULT 0 AFTER `aviso_entrega`;

ALTER TABLE `vendas`
  ADD COLUMN `modo_preco_arabes` varchar(20) NOT NULL DEFAULT 'auto' AFTER `status_pagamento`,
  ADD COLUMN `modo_preco_miniaturas` varchar(20) NOT NULL DEFAULT 'auto' AFTER `modo_preco_arabes`,
  ADD COLUMN `aviso_entrega` varchar(255) NULL AFTER `observacao`;