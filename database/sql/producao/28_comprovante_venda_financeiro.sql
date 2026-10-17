-- Comprovante da venda vira recebimento no Financeiro.
-- Quando o vendedor anexa um comprovante com valor e conta, o ERP lança uma ENTRADA no extrato
-- dessa conta (fin_movimentos) e liga as duas coisas. Requer 26 e 27.

ALTER TABLE `venda_comprovantes`
  ADD COLUMN `fin_conta_id` int unsigned NULL AFTER `conta`,
  ADD COLUMN `fin_movimento_id` bigint unsigned NULL AFTER `fin_conta_id`,
  ADD COLUMN `data_pagamento` date NULL AFTER `valor`,
  ADD KEY `idx_venda_comprovantes_mov` (`fin_movimento_id`);

ALTER TABLE `fin_movimentos`
  ADD COLUMN `venda_id` int unsigned NULL AFTER `conta_receber_id`,
  ADD KEY `idx_fin_mov_venda` (`venda_id`);
