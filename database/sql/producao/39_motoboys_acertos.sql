-- Motoboys das rotas (tabela "entregadores": a "motoboys" antiga é de outro módulo) e acerto do frete (repasse) com conta a pagar automática. Requer 26 e 29.
--   semanal: rotas de sábado a sexta, uma conta a pagar por motoboy vencendo no sábado seguinte;
--   diário:  motoboy avulso, recebe no mesmo dia (uma conta por dia).
-- Fretes das entregas = repasse (não é despesa); extras do motoboy (2ª caixa, entrega que deu errado…) = despesa.

CREATE TABLE IF NOT EXISTS `entregadores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `nome` varchar(120) NOT NULL,
  `telefone` varchar(30) NULL,
  `acerto` varchar(10) NOT NULL DEFAULT 'semanal',   -- semanal | diario
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entregadores_empresa` (`empresa_id`, `ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entregador_acertos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `entregador_id` int unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,                    -- semanal | diario
  `periodo_inicio` date NOT NULL,
  `periodo_fim` date NOT NULL,
  `vencimento` date NOT NULL,
  `valor_fretes` decimal(12,2) NOT NULL DEFAULT 0,
  `valor_extras` decimal(12,2) NOT NULL DEFAULT 0,
  `conta_fretes_id` int unsigned NULL,            -- conta a pagar do repasse (não é despesa)
  `conta_extras_id` int unsigned NULL,            -- conta a pagar dos extras (despesa)
  `status` varchar(10) NOT NULL DEFAULT 'aberto', -- aberto | pago
  `pago_em` datetime NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_macerto` (`empresa_id`, `entregador_id`, `periodo_inicio`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `rotas_entrega`
  ADD COLUMN `entregador_id` int unsigned NULL AFTER `motoboy_nome`,
  ADD COLUMN `acerto_id` int unsigned NULL AFTER `entregador_id`,
  ADD KEY `idx_rotas_acerto` (`acerto_id`);

ALTER TABLE `rota_entrega_itens`
  ADD COLUMN `motoboy_extra` decimal(10,2) NULL AFTER `frete`,
  ADD COLUMN `motoboy_extra_motivo` varchar(120) NULL AFTER `motoboy_extra`;
