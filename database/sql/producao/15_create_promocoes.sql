-- Módulo de promoções por produto (preço promocional com vigência e limite de unidades)
-- Compatível com migration:
--   2026_08_26_100000_create_promocoes_table.php

CREATE TABLE `promocoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `produto_id` int unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'percentual',
  `valor` decimal(12,4) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `quantidade_limite` int unsigned DEFAULT NULL,
  `quantidade_utilizada` int unsigned NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promocoes_empresa_id_foreign` (`empresa_id`),
  KEY `promocoes_produto_id_ativo_index` (`produto_id`,`ativo`),
  KEY `promocoes_data_inicio_data_fim_index` (`data_inicio`,`data_fim`),
  CONSTRAINT `promocoes_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promocoes_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
