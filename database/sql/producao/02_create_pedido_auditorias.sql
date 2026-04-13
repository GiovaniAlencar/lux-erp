-- Log de ações no pedido (criação, impressão, itens, estorno, etc.)
-- Compatível com migration: 2026_04_08_180800_create_pedido_auditorias_table.php

CREATE TABLE `pedido_auditorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `pedido_id` int unsigned NOT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `acao` varchar(60) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_auditorias_pedido_id_index` (`pedido_id`),
  KEY `pedido_auditorias_empresa_id_index` (`empresa_id`),
  CONSTRAINT `pedido_auditorias_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_auditorias_pedido_id_foreign` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_auditorias_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
