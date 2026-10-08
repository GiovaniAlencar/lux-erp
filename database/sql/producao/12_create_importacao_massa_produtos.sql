-- Importação em massa de produtos (estoque, custo, preços)
-- Verificar: SHOW TABLES LIKE 'importacao_massa_produtos';

CREATE TABLE IF NOT EXISTS importacao_massa_produtos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    arquivo_nome VARCHAR(255) NULL,
    qtd_itens INT UNSIGNED NOT NULL DEFAULT 0,
    qtd_itens_validos INT UNSIGNED NOT NULL DEFAULT 0,
    qtd_itens_erro INT UNSIGNED NOT NULL DEFAULT 0,
    qtd_unidades DECIMAL(14,3) NOT NULL DEFAULT 0,
    valor_total_compra DECIMAL(14,2) NOT NULL DEFAULT 0,
    confirmado_em TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS importacao_massa_produto_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    importacao_id BIGINT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NULL,
    codigo_informado INT UNSIGNED NOT NULL,
    produto_nome VARCHAR(255) NULL,
    estoque_anterior DECIMAL(14,3) NOT NULL DEFAULT 0,
    quantidade DECIMAL(14,3) NOT NULL DEFAULT 0,
    estoque_final DECIMAL(14,3) NOT NULL DEFAULT 0,
    custo_anterior DECIMAL(14,2) NULL,
    custo_novo DECIMAL(14,2) NULL,
    preco_1_anterior DECIMAL(14,2) NULL,
    preco_1_novo DECIMAL(14,2) NULL,
    preco_2_anterior DECIMAL(14,2) NULL,
    preco_2_novo DECIMAL(14,2) NULL,
    preco_3_anterior DECIMAL(14,2) NULL,
    preco_3_novo DECIMAL(14,2) NULL,
    valor_linha DECIMAL(14,2) NOT NULL DEFAULT 0,
    valido TINYINT(1) NOT NULL DEFAULT 1,
    erro VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_imp_massa_itens_importacao
        FOREIGN KEY (importacao_id) REFERENCES importacao_massa_produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
