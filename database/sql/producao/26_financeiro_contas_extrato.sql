-- Financeiro: contas (banco, caixa, cartão…) com saldo e extrato.
-- Cada pagamento/recebimento/lançamento vira uma linha em fin_movimentos (com comprovante opcional).
-- Saldo da conta = saldo inicial + entradas − saídas.

CREATE TABLE IF NOT EXISTS `fin_contas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `nome` varchar(80) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'banco',          -- banco | caixa | cartao | outro
  `saldo_inicial` decimal(14,2) NOT NULL DEFAULT 0,
  `data_saldo_inicial` date NULL,
  `padrao` tinyint(1) NOT NULL DEFAULT 0,               -- conta sugerida nos pagamentos
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fin_contas_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_movimentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `conta_id` int unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,                          -- entrada | saida
  `valor` decimal(14,2) NOT NULL,                       -- sempre positivo
  `data` date NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `origem` varchar(20) NOT NULL DEFAULT 'avulso',       -- pagamento | recebimento | avulso | transferencia | ajuste
  `categoria_id` int unsigned NULL,
  `forma_pagamento` varchar(30) NULL,
  `conta_pagar_id` int unsigned NULL,
  `conta_receber_id` int unsigned NULL,
  `transferencia` char(36) NULL,                        -- liga as duas pernas de uma transferência
  `comprovante` varchar(255) NULL,                      -- caminho em storage/app (privado)
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fin_mov_conta_data` (`conta_id`, `data`),
  KEY `idx_fin_mov_empresa_data` (`empresa_id`, `data`),
  KEY `idx_fin_mov_pagar` (`conta_pagar_id`),
  KEY `idx_fin_mov_receber` (`conta_receber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
