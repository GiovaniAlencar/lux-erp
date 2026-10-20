-- Quando um comprovante de venda é excluído, a entrada no extrato NÃO some mais:
-- fica marcada com uma pendência para o financeiro decidir (estornar ou vincular a outra venda).
-- Requer 26 e 28.

ALTER TABLE `fin_movimentos`
  ADD COLUMN `pendencia` varchar(255) NULL AFTER `comprovante`,
  ADD COLUMN `pendencia_em` datetime NULL AFTER `pendencia`,
  ADD KEY `idx_fin_mov_pendencia` (`empresa_id`, `pendencia_em`);
