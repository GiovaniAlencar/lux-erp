-- Módulo de rotas de entrega (tabelas + campos de pagamento/motoboy)
-- Compatível com migrations:
--   2026_06_09_140000_create_rotas_entrega_tables.php
--   2026_06_09_150000_add_motoboy_nome_to_rotas_entrega_table.php
--   2026_06_09_160000_add_pagamento_fields_to_rota_entrega_itens.php

CREATE TABLE `rotas_entrega` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'rascunho',
  `motoboy_nome` varchar(120) DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rotas_entrega_empresa_id_foreign` (`empresa_id`),
  KEY `rotas_entrega_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `rotas_entrega_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rotas_entrega_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rota_entrega_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rota_entrega_id` int unsigned NOT NULL,
  `venda_id` int unsigned NOT NULL,
  `ordem` smallint unsigned NOT NULL DEFAULT 0,
  `cliente_nome` varchar(255) NOT NULL,
  `telefone` varchar(40) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(30) DEFAULT NULL,
  `bairro` varchar(120) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `valor_total` decimal(16,2) NOT NULL DEFAULT 0.00,
  `frete` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qtd_parcelas` smallint unsigned NOT NULL DEFAULT 1,
  `mostrar_parcelas` tinyint(1) NOT NULL DEFAULT 0,
  `mostrar_valor_pedido` tinyint(1) NOT NULL DEFAULT 0,
  `situacao_pagamento` varchar(30) NOT NULL DEFAULT 'pagamento_entrega',
  `valor_restante` decimal(16,2) DEFAULT NULL,
  `valor_parcela` decimal(16,2) DEFAULT NULL,
  `tipo_pagamento` varchar(2) DEFAULT NULL,
  `tipo_pagamento_nome` varchar(80) DEFAULT NULL,
  `prioridade` tinyint(1) NOT NULL DEFAULT 0,
  `observacao_prioridade` varchar(500) DEFAULT NULL,
  `status_entrega` varchar(20) NOT NULL DEFAULT 'pendente',
  `ocorrencia_tipo` varchar(60) DEFAULT NULL,
  `ocorrencia_observacao` text,
  `token_confirmacao` varchar(64) NOT NULL,
  `confirmado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rota_entrega_itens_token_confirmacao_unique` (`token_confirmacao`),
  KEY `rota_entrega_itens_rota_entrega_id_foreign` (`rota_entrega_id`),
  KEY `rota_entrega_itens_venda_id_foreign` (`venda_id`),
  KEY `rota_entrega_itens_venda_id_status_entrega_index` (`venda_id`,`status_entrega`),
  CONSTRAINT `rota_entrega_itens_rota_entrega_id_foreign` FOREIGN KEY (`rota_entrega_id`) REFERENCES `rotas_entrega` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rota_entrega_itens_venda_id_foreign` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
