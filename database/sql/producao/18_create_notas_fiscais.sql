-- NF-e emitida pelo ERP a partir da parte fiscal da venda (rascunho editável -> transmitida).
-- Compatível com migration: 2026_10_09_100000_create_notas_fiscais_tables.php
-- Só cria tabelas novas. Antes de executar: backup.

CREATE TABLE `notas_fiscais` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `venda_id` int unsigned NULL,
  `cliente_id` int unsigned NOT NULL,
  `natureza_id` int unsigned NULL,
  `usuario_id` int unsigned NULL,
  `status` varchar(20) NOT NULL DEFAULT 'rascunho',
  `ambiente` tinyint unsigned NOT NULL DEFAULT 2,
  `serie` smallint unsigned NULL,
  `numero` int unsigned NULL,
  `chave` varchar(44) NULL,
  `protocolo` varchar(20) NULL,
  `data_emissao` datetime NULL,
  `autorizada_em` datetime NULL,
  `valor_produtos` decimal(16,2) NOT NULL DEFAULT 0,
  `valor_frete` decimal(16,2) NOT NULL DEFAULT 0,
  `valor_desconto` decimal(16,2) NOT NULL DEFAULT 0,
  `valor_outros` decimal(16,2) NOT NULL DEFAULT 0,
  `valor_total` decimal(16,2) NOT NULL DEFAULT 0,
  `tipo_pagamento` varchar(2) NOT NULL DEFAULT '17',
  `info_complementar` text NULL,
  `ultimo_retorno` text NULL,
  `cancelada_em` datetime NULL,
  `motivo_cancelamento` varchar(255) NULL,
  `sequencia_cce` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `notas_fiscais_venda_id_index` (`venda_id`),
  KEY `notas_fiscais_status_index` (`status`),
  KEY `notas_fiscais_chave_index` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nota_fiscal_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nota_fiscal_id` int unsigned NOT NULL,
  `produto_id` int unsigned NOT NULL,
  `item_venda_id` int unsigned NULL,
  `descricao` varchar(150) NOT NULL,
  `quantidade` decimal(14,4) NOT NULL,
  `valor_unitario` decimal(16,4) NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `nota_fiscal_itens_nota_fiscal_id_index` (`nota_fiscal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
