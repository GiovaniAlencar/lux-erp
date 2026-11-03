-- Fechamento do mês: guarda o que você informa (divisão entre sócios, retiradas, reinvestimento,
-- valores extras) e, ao fechar, uma "foto" dos números calculados. Requer 26, 29 e 33.
CREATE TABLE IF NOT EXISTS `fechamentos_mes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `mes` char(7) NOT NULL,                       -- 2026-09
  `entradas` longtext NULL,                     -- JSON com o que foi informado à mão
  `snapshot` longtext NULL,                     -- JSON com os números no momento em que foi fechado
  `status` varchar(10) NOT NULL DEFAULT 'aberto', -- aberto | fechado
  `fechado_em` datetime NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fechamento_mes` (`empresa_id`, `mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
