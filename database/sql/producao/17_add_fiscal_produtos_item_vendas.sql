-- Divisão fiscal / não fiscal da venda (cobrança em duas contas) + controle da NF-e externa.
-- Compatível com migrations:
--   2026_10_08_100000_add_fiscal_to_produtos_e_item_vendas.php
--   2026_10_08_110000_add_nf_externa_to_vendas.php
-- Só adiciona colunas. Todos os produtos e vendas existentes ficam como NÃO FISCAL (0).
-- Antes de executar: faça backup do banco.

-- 1) Produto fiscal (NF-e emitida no outro sistema)
ALTER TABLE `produtos`
  ADD COLUMN IF NOT EXISTS `fiscal` tinyint(1) NOT NULL DEFAULT 0 AFTER `valor_venda`;

-- 2) Cópia da marcação no item, no momento da venda
ALTER TABLE `item_vendas`
  ADD COLUMN IF NOT EXISTS `fiscal` tinyint(1) NOT NULL DEFAULT 0 AFTER `valor_custo`;

-- 3) Controle da NF-e emitida no outro sistema
ALTER TABLE `vendas`
  ADD COLUMN IF NOT EXISTS `nf_externa_status` varchar(20) NULL,
  ADD COLUMN IF NOT EXISTS `nf_externa_numero` varchar(30) NULL,
  ADD COLUMN IF NOT EXISTS `nf_externa_valor` decimal(16,2) NULL,
  ADD COLUMN IF NOT EXISTS `nf_externa_em` timestamp NULL,
  ADD COLUMN IF NOT EXISTS `nf_externa_usuario_id` int unsigned NULL;

-- 4) Garante tudo como não fiscal (redundante com o DEFAULT 0, mas deixa explícito)
UPDATE `produtos` SET `fiscal` = 0;
UPDATE `item_vendas` SET `fiscal` = 0;
