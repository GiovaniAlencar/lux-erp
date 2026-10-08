-- Preços atacado 1 e 2 (valor_venda = preço normal)
-- Verificar antes: SHOW COLUMNS FROM produtos LIKE 'preco_2';
-- Se não retornar linhas, execute o ALTER abaixo.

ALTER TABLE produtos
    ADD COLUMN preco_2 DECIMAL(10,2) NULL AFTER valor_venda,
    ADD COLUMN preco_3 DECIMAL(10,2) NULL AFTER preco_2;
