-- Saldo de estoque FISCAL (unidades com nota de entrada).
-- Compatível com migration: 2026_10_09_120000_create_estoque_fiscal.php
-- Antes de executar: backup.

ALTER TABLE `produtos`
  ADD COLUMN `estoque_fiscal` decimal(14,3) NOT NULL DEFAULT 0 AFTER `fiscal`;

ALTER TABLE `item_vendas`
  ADD COLUMN `qtd_fiscal` decimal(14,3) NOT NULL DEFAULT 0 AFTER `fiscal`;

-- itens que já estavam marcados como fiscais: a quantidade inteira foi fiscal
UPDATE `item_vendas` SET `qtd_fiscal` = `quantidade` WHERE `fiscal` = 1;

CREATE TABLE `estoque_fiscal_movimentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `produto_id` int unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `quantidade` decimal(14,3) NOT NULL,
  `saldo_apos` decimal(14,3) NOT NULL,
  `venda_id` int unsigned NULL,
  `chave` varchar(44) NULL,
  `documento` varchar(60) NULL,
  `observacao` varchar(255) NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `estoque_fiscal_movimentos_produto_id_index` (`produto_id`),
  KEY `estoque_fiscal_movimentos_chave_index` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
