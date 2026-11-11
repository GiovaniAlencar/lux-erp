-- Linha nacional: a categoria "Ciclo" vira "Nacionais" e passa a ter também a Paris Elysees.
-- Só dados (não muda tabelas). O site mostra a vitrine "Nacionais" (aceita os dois nomes)
-- e o catálogo PDF separa Ciclo e Paris Elysees pela marca.
-- Rode um passo de cada vez e confira.

-- 1) CONFIRA os produtos da Paris Elysees (marca no ERP ou "Paris Elysees" no nome) e a categoria de hoje:
SELECT p.id, p.nome, m.nome AS marca, c.nome AS categoria_atual
FROM produtos p
LEFT JOIN marcas m ON m.id = p.marca_id
LEFT JOIN categorias c ON c.id = p.categoria_id
WHERE m.nome LIKE '%paris%elys%' OR p.nome LIKE '%paris%elys%'
ORDER BY p.nome;

-- 2) Se AINDA NÃO existe uma categoria "Nacionais", a "Ciclo" é renomeada para "Nacionais"
--    (os produtos da Ciclo continuam nela, nada muda para eles):
UPDATE categorias c
SET c.nome = 'Nacionais', c.updated_at = NOW()
WHERE c.nome = 'Ciclo'
  AND NOT EXISTS (SELECT 1 FROM (SELECT empresa_id FROM categorias WHERE nome = 'Nacionais') n WHERE n.empresa_id = c.empresa_id);

--    Se JÁ existia "Nacionais" (criada à mão), junta os produtos da "Ciclo" nela:
UPDATE produtos p
JOIN categorias ciclo ON ciclo.id = p.categoria_id AND ciclo.nome = 'Ciclo'
JOIN categorias nac ON nac.empresa_id = ciclo.empresa_id AND nac.nome = 'Nacionais'
SET p.categoria_id = nac.id;

-- 3) move a Paris Elysees (hoje em Árabes) para "Nacionais":
UPDATE produtos p
LEFT JOIN marcas m ON m.id = p.marca_id
JOIN categorias nac ON nac.empresa_id = p.empresa_id AND nac.nome = 'Nacionais'
SET p.categoria_id = nac.id
WHERE m.nome LIKE '%paris%elys%' OR p.nome LIKE '%paris%elys%';

-- 4) confira: Ciclo e Paris Elysees na mesma categoria
SELECT c.nome AS categoria, COALESCE(m.nome, '(sem marca)') AS marca, COUNT(*) AS produtos
FROM produtos p
JOIN categorias c ON c.id = p.categoria_id
LEFT JOIN marcas m ON m.id = p.marca_id
WHERE c.nome IN ('Nacionais', 'Ciclo')
GROUP BY c.nome, m.nome
ORDER BY c.nome, m.nome;

-- 5) (opcional) a categoria "Ciclo" ficou vazia? pode apagar pelo ERP em Cadastros › Categorias.
