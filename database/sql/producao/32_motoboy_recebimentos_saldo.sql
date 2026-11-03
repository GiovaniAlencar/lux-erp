-- App do motoboy (link único da rota), recebimento na entrega com foto do comprovante,
-- coordenadas para ordenar as entregas e saldo do cliente (pagou a mais / usou o crédito).
-- Requer 26, 27 e 28.

-- link único da rota para o motoboy (o mesmo link mostra todas as entregas)
ALTER TABLE `rotas_entrega`
  ADD COLUMN `token_motoboy` varchar(64) NULL AFTER `observacao`,
  ADD UNIQUE KEY `uq_rotas_token_motoboy` (`token_motoboy`);

-- coordenadas da entrega e o que o motoboy recebeu
ALTER TABLE `rota_entrega_itens`
  ADD COLUMN `lat` decimal(10,7) NULL AFTER `complemento`,
  ADD COLUMN `lng` decimal(10,7) NULL AFTER `lat`,
  ADD COLUMN `geo_status` varchar(12) NULL AFTER `lng`,            -- ok | falhou
  ADD COLUMN `recebido_forma` varchar(20) NULL AFTER `confirmado_em`, -- pix | dinheiro | credito | debito
  ADD COLUMN `recebido_valor` decimal(14,2) NULL AFTER `recebido_forma`,  -- o que o cliente pagou (bruto)
  ADD COLUMN `recebido_liquido` decimal(14,2) NULL AFTER `recebido_valor`, -- o que entra na conta
  ADD COLUMN `recebido_bandeira` varchar(20) NULL AFTER `recebido_liquido`,
  ADD COLUMN `recebido_parcelas` tinyint unsigned NULL AFTER `recebido_bandeira`,
  ADD COLUMN `recebido_comprovante` varchar(255) NULL AFTER `recebido_parcelas`;

-- cache dos endereços já convertidos em coordenadas (OpenStreetMap), para não consultar de novo
CREATE TABLE IF NOT EXISTS `geo_enderecos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chave` char(40) NOT NULL,                 -- sha1 do endereço normalizado
  `endereco` varchar(400) NOT NULL,
  `lat` decimal(10,7) NULL,
  `lng` decimal(10,7) NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_geo_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- saldo do cliente: cada linha é um crédito (pagou a mais) ou um débito (usou o saldo)
CREATE TABLE IF NOT EXISTS `cliente_saldo_movimentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `cliente_id` int unsigned NOT NULL,
  `venda_id` int unsigned NULL,
  `venda_comprovante_id` bigint unsigned NULL, -- recebimento que gerou/usou o saldo (excluir o recebimento desfaz)
  `tipo` varchar(10) NOT NULL,               -- credito | debito
  `valor` decimal(14,2) NOT NULL,
  `descricao` varchar(255) NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saldo_cliente` (`empresa_id`, `cliente_id`),
  KEY `idx_saldo_venda` (`venda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
