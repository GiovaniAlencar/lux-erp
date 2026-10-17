-- Comprovantes de pagamento anexados à venda pelo vendedor (PIX, transferência, maquininha…).
-- O arquivo fica em storage/app/comprovantes-vendas (privado, não fica em public/).
-- Uma venda pode ter vários (ex.: um PIX na conta fiscal e outro na conta 2).

CREATE TABLE IF NOT EXISTS `venda_comprovantes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `venda_id` int unsigned NOT NULL,
  `usuario_id` int unsigned NULL,                       -- quem anexou
  `arquivo` varchar(255) NOT NULL,                      -- caminho no disco local (privado)
  `nome_original` varchar(150) NULL,
  `conta` varchar(20) NULL,                             -- fiscal | nao_fiscal | outra
  `valor` decimal(14,2) NULL,
  `observacao` varchar(255) NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venda_comprovantes_venda` (`empresa_id`, `venda_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
