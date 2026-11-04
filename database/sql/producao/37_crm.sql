-- CRM LUX (fase 2): campanhas, ciclos de contato, fila de envios, eventos da central, bloqueios e configuração.
-- Nesta fase NADA é enviado: a fila só é montada e conferida na tela.
-- Telefones sempre só com dígitos, com 55 + DDD (ex.: 5583999999999).

CREATE TABLE IF NOT EXISTS `crm_campanhas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `chave` varchar(30) NOT NULL,                 -- reposicao | esfriando | inativo | interessado
  `nome` varchar(80) NOT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 0,
  `dias_min` int NOT NULL DEFAULT 0,             -- dias desde a última compra (ou horas, no "interessado") para entrar
  `dias_max` int NULL,                           -- passou disso, sai desta campanha (vai para a próxima faixa)
  `texto_1` text NULL,                           -- 1ª tentativa; variações separadas por uma linha com ---
  `texto_2` text NULL,                           -- 2ª tentativa
  `ordem` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_camp` (`empresa_id`, `chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- interessados que vieram da central (sem obrigar cadastro de cliente)
CREATE TABLE IF NOT EXISTS `crm_oportunidades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `nome` varchar(150) NULL,
  `cliente_id` int unsigned NULL,               -- quando o telefone bate com um cadastro
  `classificacao` varchar(30) NOT NULL,          -- interessado | orcamento_enviado | aguardando_decisao | nao_comprou
  `motivo` varchar(255) NULL,
  `atendente` varchar(120) NULL,
  `conversa_externa_id` varchar(64) NULL,        -- id da conversa na central
  `data_conversa` datetime NULL,
  `status` varchar(20) NOT NULL DEFAULT 'aberta', -- aberta | em_campanha | encerrada
  `evento_id` varchar(64) NULL,                  -- idempotência do aviso da central
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_opo_evento` (`evento_id`),
  KEY `idx_crm_opo_tel` (`empresa_id`, `telefone`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- uma pessoa dentro de uma campanha (do início ao fim do ciclo de 2 tentativas)
CREATE TABLE IF NOT EXISTS `crm_ciclos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `campanha_id` int unsigned NOT NULL,
  `cliente_id` int unsigned NULL,
  `oportunidade_id` int unsigned NULL,
  `telefone` varchar(20) NOT NULL,
  `nome` varchar(150) NULL,
  `referencia` varchar(40) NOT NULL,             -- evita repetir: venda que originou (ex.: v123) ou oportunidade (o45)
  `motivo` varchar(255) NULL,                    -- por que entrou (texto para a tela)
  `contexto` json NULL,                          -- última compra, valor, produtos, dias
  `status` varchar(20) NOT NULL DEFAULT 'ativo', -- ativo | respondido | convertido | encerrado | bloqueado | cancelado
  `motivo_fim` varchar(120) NULL,
  `ativo_telefone` varchar(20) GENERATED ALWAYS AS (IF(`status` IN ('ativo','respondido'), `telefone`, NULL)) STORED,
  `respondido_em` datetime NULL,
  `encerrado_em` datetime NULL,
  `venda_id` int unsigned NULL,                  -- pedido atribuído ao CRM
  `venda_valor` decimal(14,2) NULL,
  `atendente` varchar(120) NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_ciclo_ref` (`empresa_id`, `campanha_id`, `telefone`, `referencia`),
  UNIQUE KEY `uq_crm_ciclo_ativo` (`empresa_id`, `ativo_telefone`),   -- um ciclo ativo por telefone
  KEY `idx_crm_ciclo_status` (`empresa_id`, `status`),
  KEY `idx_crm_ciclo_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fila e histórico: cada tentativa é um envio
CREATE TABLE IF NOT EXISTS `crm_envios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `ciclo_id` int unsigned NOT NULL,
  `tentativa` tinyint unsigned NOT NULL,         -- 1 ou 2
  `uuid` char(36) NOT NULL,                      -- identificador único do envio (idempotência com a central)
  `telefone` varchar(20) NOT NULL,
  `texto` text NOT NULL,
  `agendado_para` datetime NOT NULL,
  `status` varchar(25) NOT NULL DEFAULT 'aguardando_aprovacao',
      -- aguardando_aprovacao | pendente | processando | enviado | entregue | respondido | cancelado | erro
  `enviado_em` datetime NULL,
  `id_externo` varchar(80) NULL,                 -- id da mensagem na Evolution (vem da central)
  `erro` varchar(500) NULL,
  `tentativas_envio` tinyint unsigned NOT NULL DEFAULT 0,
  `aprovado_por` int unsigned NULL,
  `aprovado_em` datetime NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_envio` (`ciclo_id`, `tentativa`),
  UNIQUE KEY `uq_crm_envio_uuid` (`uuid`),
  KEY `idx_crm_envio_fila` (`empresa_id`, `status`, `agendado_para`),
  KEY `idx_crm_envio_tel` (`telefone`, `enviado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tudo que a central avisar (fase 3); evento_id único impede processar o mesmo aviso duas vezes
CREATE TABLE IF NOT EXISTS `crm_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `evento_id` varchar(64) NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `telefone` varchar(20) NULL,
  `ciclo_id` int unsigned NULL,
  `envio_uuid` char(36) NULL,
  `dados` json NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_evento` (`evento_id`),
  KEY `idx_crm_evento_ciclo` (`ciclo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- quem pediu para não receber mensagens (vale para todas as campanhas)
CREATE TABLE IF NOT EXISTS `crm_bloqueios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `origem` varchar(30) NOT NULL,                 -- cliente | erp | central
  `observacao` varchar(255) NULL,
  `usuario_id` int unsigned NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_bloqueio` (`empresa_id`, `telefone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_config` (
  `empresa_id` int unsigned NOT NULL,
  `dados` json NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
