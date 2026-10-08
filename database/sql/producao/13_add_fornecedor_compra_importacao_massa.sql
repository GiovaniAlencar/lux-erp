-- Fornecedor e compra vinculados à importação em massa
-- Verificar: SHOW COLUMNS FROM importacao_massa_produtos LIKE 'fornecedor_id';

ALTER TABLE importacao_massa_produtos
    ADD COLUMN fornecedor_id INT UNSIGNED NULL AFTER usuario_id,
    ADD COLUMN compra_id INT UNSIGNED NULL AFTER fornecedor_id;
