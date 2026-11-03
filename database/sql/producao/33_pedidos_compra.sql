-- Pedidos de compra com controle do começo ao fim:
--   rascunho/pedido (em dólar ou real) → pagamentos (cotação + % do frete, saindo de uma conta)
--   → fretes/transportadora → custo final rateado → entrada no estoque → pendências (faltas, descontos).
-- Não mexe nas tabelas antigas de compras. Requer 26 (financeiro).

CREATE TABLE IF NOT EXISTS `compra_pedidos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `fornecedor_id` int unsigned NULL,
  `fornecedor_nome` varchar(150) NULL,          -- quando o fornecedor não está cadastrado
  `moeda` char(3) NOT NULL DEFAULT 'USD',       -- USD | BRL
  `status` varchar(20) NOT NULL DEFAULT 'rascunho', -- rascunho | pedido | recebido | cancelado
  `data_pedido` date NULL,
  `previsao_pagamento` date NULL,
  `cotacao_prevista` decimal(10,4) NULL,        -- para estimar o valor em reais antes de pagar
  `pct_frete_previsto` decimal(6,2) NULL,
  `total_moeda` decimal(14,2) NOT NULL DEFAULT 0,
  `observacao` text NULL,
  `compra_id` int unsigned NULL,                 -- registro em "Compras" criado na entrada
  `entrada_estoque` tinyint(1) NULL,             -- 1 = entrou no estoque por aqui · 0 = entrada pelo XML da nota
  `recebido_em` datetime NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cped_empresa_status` (`empresa_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compra_pedido_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int unsigned NOT NULL,
  `produto_id` int unsigned NULL,
  `descricao` varchar(255) NULL,                -- nome do fornecedor / item ainda sem cadastro
  `quantidade` decimal(12,2) NOT NULL,
  `preco_moeda` decimal(14,4) NOT NULL,          -- preço unitário na moeda do pedido
  `qtd_recebida` decimal(12,2) NULL,
  `custo_final` decimal(14,4) NULL,              -- custo unitário em R$ calculado na entrada
  PRIMARY KEY (`id`),
  KEY `idx_cpi_pedido` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tudo que saiu de dinheiro por causa do pedido: pagamento ao fornecedor, frete, transportadora, taxas
CREATE TABLE IF NOT EXISTS `compra_pedido_custos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,                   -- pagamento | frete | transportadora | outro
  `data` date NOT NULL,
  `valor_moeda` decimal(14,2) NULL,              -- pagamento: quanto do pedido (em dólar) foi pago
  `cotacao` decimal(10,4) NULL,
  `pct_frete` decimal(6,2) NULL,
  `valor_brl` decimal(14,2) NOT NULL,            -- o que saiu da conta, em reais
  `descricao` varchar(255) NULL,
  `fin_conta_id` int unsigned NULL,
  `fin_movimento_id` bigint unsigned NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpc_pedido` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- faltas, avarias e descontos a receber do fornecedor
CREATE TABLE IF NOT EXISTS `compra_pedido_pendencias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int unsigned NOT NULL,
  `item_id` int unsigned NULL,
  `tipo` varchar(20) NOT NULL,                   -- falta | avaria | desconto | outro
  `descricao` varchar(255) NULL,
  `quantidade` decimal(12,2) NULL,
  `valor_moeda` decimal(14,2) NULL,
  `valor_brl` decimal(14,2) NULL,
  `status` varchar(12) NOT NULL DEFAULT 'aberta', -- aberta | resolvida
  `resolucao` varchar(255) NULL,
  `resolvido_em` datetime NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpp_pedido` (`pedido_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
