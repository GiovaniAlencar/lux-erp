-- "Esqueci minha senha" do site: o link vai por e-mail; aqui fica só o hash do token.
-- (A API também cria esta tabela sozinha se não existir.)
CREATE TABLE IF NOT EXISTS `ecommerce_password_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ecommerce_user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ip` varchar(64) NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token_hash`),
  KEY `idx_user` (`ecommerce_user_id`),
  KEY `idx_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
