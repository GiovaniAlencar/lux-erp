-- DIAGNÓSTICO DE ESTOQUE E CUSTOS — só consulta (SELECT), não altera nada.
-- Rode tudo e me mande o resultado de cada bloco.
-- Se sua empresa não for a 1, troque aqui:
SET @emp = 1;

-- 1) RESUMO GERAL
SELECT
  (SELECT COUNT(*) FROM produtos WHERE empresa_id = @emp)                                        AS produtos_total,
  (SELECT COUNT(*) FROM produtos p JOIN estoques e ON e.produto_id = p.id WHERE p.empresa_id = @emp AND e.quantidade > 0) AS produtos_com_estoque,
  (SELECT COUNT(*) FROM (SELECT produto_id FROM estoques GROUP BY produto_id HAVING COUNT(*) > 1) x) AS produtos_com_2_linhas_estoque,
  (SELECT COUNT(*) FROM estoques WHERE quantidade < 0)                                         AS linhas_estoque_negativo,
  (SELECT ROUND(SUM(e.quantidade * COALESCE(p.valor_compra,0)),2) FROM estoques e JOIN produtos p ON p.id = e.produto_id WHERE p.empresa_id = @emp AND e.quantidade > 0) AS custo_estoque_positivo,
  (SELECT ROUND(SUM(e.quantidade * COALESCE(p.valor_compra,0)),2) FROM estoques e JOIN produtos p ON p.id = e.produto_id WHERE p.empresa_id = @emp AND e.quantidade < 0) AS custo_estoque_negativo,
  (SELECT COUNT(*) FROM estoques e JOIN produtos p ON p.id = e.produto_id WHERE p.empresa_id = @emp AND e.quantidade > 0 AND COALESCE(p.valor_compra,0) <= 0) AS com_estoque_sem_custo,
  (SELECT COUNT(*) FROM estoques e JOIN produtos p ON p.id = e.produto_id WHERE p.empresa_id = @emp AND e.quantidade > 0 AND p.valor_venda > 0 AND p.valor_compra > p.valor_venda) AS custo_maior_que_preco,
  (SELECT COUNT(*) FROM produtos p LEFT JOIN (SELECT produto_id, SUM(quantidade) q FROM estoques GROUP BY produto_id) e ON e.produto_id = p.id WHERE p.empresa_id = @emp AND p.estoque_fiscal > COALESCE(e.q,0) + 0.001) AS fiscal_maior_que_estoque;

-- 2) PRODUTOS COM ESTOQUE E SEM CUSTO (ficam de fora do valor do estoque)
SELECT p.id, p.nome, e.quantidade, p.valor_compra, p.valor_venda
FROM estoques e JOIN produtos p ON p.id = e.produto_id
WHERE p.empresa_id = @emp AND e.quantidade > 0 AND COALESCE(p.valor_compra,0) <= 0
ORDER BY e.quantidade DESC LIMIT 50;

-- 3) CUSTO ESTRANHO: maior que o preço de venda, ou muito diferente da última compra
SELECT p.id, p.nome, e.quantidade, p.valor_compra AS custo_atual, p.valor_venda AS preco,
       ult.valor_unitario AS ultima_compra, DATE(ult.created_at) AS data_ultima_compra
FROM produtos p
JOIN estoques e ON e.produto_id = p.id AND e.quantidade > 0
LEFT JOIN (
  SELECT ic.produto_id, ic.valor_unitario, ic.created_at
  FROM item_compras ic
  JOIN (SELECT produto_id, MAX(id) id FROM item_compras GROUP BY produto_id) m ON m.id = ic.id
) ult ON ult.produto_id = p.id
WHERE p.empresa_id = @emp
  AND ( (p.valor_venda > 0 AND p.valor_compra > p.valor_venda)
     OR (ult.valor_unitario > 0 AND (p.valor_compra > ult.valor_unitario * 1.6 OR p.valor_compra < ult.valor_unitario * 0.6)) )
ORDER BY e.quantidade * p.valor_compra DESC LIMIT 60;

-- 4) SALDO FISCAL (com nota) MAIOR QUE O ESTOQUE NORMAL — a entrada no estoque normal pode ter ficado faltando
SELECT p.id, p.nome, p.estoque_fiscal, COALESCE(e.q,0) AS estoque_normal
FROM produtos p LEFT JOIN (SELECT produto_id, SUM(quantidade) q FROM estoques GROUP BY produto_id) e ON e.produto_id = p.id
WHERE p.empresa_id = @emp AND p.estoque_fiscal > COALESCE(e.q,0) + 0.001
ORDER BY p.estoque_fiscal - COALESCE(e.q,0) DESC LIMIT 50;

-- 5) MESMA NOTA LANÇADA MAIS DE UMA VEZ NO ESTOQUE NORMAL (pode ter dobrado estoque)
SELECT c.chave, COUNT(*) AS vezes, GROUP_CONCAT(c.id) AS compras, GROUP_CONCAT(LEFT(c.observacao, 40) SEPARATOR ' | ') AS origem
FROM compras c
WHERE c.empresa_id = @emp AND c.chave IS NOT NULL AND c.chave <> ''
GROUP BY c.chave HAVING COUNT(*) > 1;

-- 6) NOTA QUE ENTROU PELO XML E TAMBÉM POR OUTRA TELA (importação de compra / DF-e)
--    Se no XML você deixou marcado "estoque normal", essa nota somou estoque DUAS vezes.
SELECT m.chave, MAX(m.documento) documento, COUNT(DISTINCT m.produto_id) produtos,
       (SELECT GROUP_CONCAT(CONCAT(c.id, ' em ', DATE(c.created_at), ' - ', LEFT(COALESCE(c.observacao,'importada'),30)) SEPARATOR ' | ')
          FROM compras c WHERE c.chave = m.chave) AS compras
FROM estoque_fiscal_movimentos m
WHERE m.empresa_id = @emp AND m.tipo = 'entrada_xml' AND m.chave IS NOT NULL
  AND EXISTS (SELECT 1 FROM compras c WHERE c.chave = m.chave AND COALESCE(c.observacao,'') NOT LIKE 'Entrada pelo XML%')
GROUP BY m.chave;

-- 7) PRODUTOS COM MAIS DE UMA LINHA DE ESTOQUE (o relatório antigo pegava só uma)
SELECT e.produto_id, p.nome, COUNT(*) linhas, GROUP_CONCAT(CONCAT(COALESCE(e.filial_id,'sem filial'), ':', e.quantidade)) detalhe
FROM estoques e JOIN produtos p ON p.id = e.produto_id
GROUP BY e.produto_id, p.nome HAVING COUNT(*) > 1 LIMIT 50;
