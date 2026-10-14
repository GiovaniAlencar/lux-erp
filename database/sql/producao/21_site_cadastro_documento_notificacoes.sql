-- Cadastro do site: CPF/CNPJ, razão social e consentimento de marketing (LGPD)
-- + fila de notificações (o site/API registra o evento, o ERP envia e-mail/WhatsApp).

ALTER TABLE `ecommerce_users`
  ADD COLUMN `tipo_pessoa` enum('PF','PJ') NULL DEFAULT NULL AFTER `phone`,
  ADD COLUMN `documento` varchar(20) NULL DEFAULT NULL AFTER `tipo_pessoa`,
  ADD COLUMN `razao_social` varchar(150) NULL DEFAULT NULL AFTER `documento`,
  ADD COLUMN `aceita_email` tinyint(1) NOT NULL DEFAULT 0 AFTER `razao_social`,
  ADD COLUMN `aceita_whatsapp` tinyint(1) NOT NULL DEFAULT 0 AFTER `aceita_email`,
  ADD COLUMN `consentimento_em` datetime NULL DEFAULT NULL AFTER `aceita_whatsapp`,
  ADD COLUMN `consentimento_ip` varchar(64) NULL DEFAULT NULL AFTER `consentimento_em`,
  ADD COLUMN `email_verificado_em` datetime NULL DEFAULT NULL AFTER `consentimento_ip`,
  ADD INDEX `idx_ecommerce_users_documento` (`documento`);

CREATE TABLE `notificacoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `evento` varchar(60) NOT NULL,              -- ex.: cadastro_recebido, pedido_recebido, senha_recuperar
  `origem` varchar(20) NOT NULL DEFAULT 'site', -- site | erp
  `ecommerce_user_id` bigint unsigned NULL,
  `cliente_id` int NULL,
  `email` varchar(150) NULL,
  `telefone` varchar(30) NULL,
  `dados` json NULL,                          -- variáveis do modelo (nome, pedido, link…)
  `status` enum('pendente','enviado','erro','ignorado') NOT NULL DEFAULT 'pendente',
  `canais_enviados` varchar(60) NULL,          -- email,whatsapp
  `tentativas` tinyint unsigned NOT NULL DEFAULT 0,
  `erro` text NULL,
  `enviar_apos` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enviado_em` datetime NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notificacoes_fila` (`status`, `enviar_apos`),
  KEY `idx_notificacoes_user` (`ecommerce_user_id`),
  KEY `idx_notificacoes_evento` (`evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
