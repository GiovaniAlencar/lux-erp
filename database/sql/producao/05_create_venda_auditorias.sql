-- Auditoria de vendas (criação, alteração de itens, workflow, caixa)
-- Compatível com migration: 2026_04_08_200000_create_venda_auditorias_table.php

CREATE TABLE `venda_auditorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `venda_id` int unsigned NOT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `acao` varchar(60) NOT NULL,
  `descricao` varchar(512) NOT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venda_auditorias_venda_id_index` (`venda_id`),
  KEY `venda_auditorias_empresa_id_index` (`empresa_id`),
  CONSTRAINT `venda_auditorias_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venda_auditorias_venda_id_foreign` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venda_auditorias_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
