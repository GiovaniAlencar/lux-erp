-- Esgotados no site + "Avise-me quando chegar".
-- Requer 21 (tabela notificacoes) aplicado antes.

-- produto que não deve aparecer no site nem como esgotado (ex.: descontinuado)
ALTER TABLE `produtos`
  ADD COLUMN `ocultar_site` tinyint(1) NOT NULL DEFAULT 0 AFTER `destaque_ate`;

-- quem pediu para ser avisado quando o produto voltar
CREATE TABLE `ecommerce_avisos_reposicao` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ecommerce_user_id` bigint unsigned NOT NULL,
  `produto_id` int unsigned NOT NULL,
  `avisado_em` datetime NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aviso_user_produto` (`ecommerce_user_id`, `produto_id`),
  KEY `idx_aviso_produto_pendente` (`produto_id`, `avisado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
