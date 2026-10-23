-- Linha nacional CICLO: categoria própria "Ciclo" no ERP (o site mostra como "Ciclo · Nacionais"
-- e ganha a vitrine "Ciclo · Linha nacional" na página inicial). Não mexe na estrutura do banco.
-- O nome tem que ser exatamente "Ciclo" (é por ele que o site encontra a categoria).

-- 1) CONFIRA ANTES quais produtos vão mudar (marca "Ciclo" no ERP ou "CICLO" no nome do produto):
SELECT p.id, p.nome, m.nome AS marca, c.nome AS categoria_atual
FROM produtos p
LEFT JOIN marcas m ON m.id = p.marca_id
LEFT JOIN categorias c ON c.id = p.categoria_id
WHERE m.nome LIKE '%ciclo%' OR p.nome LIKE '%ciclo%'
ORDER BY c.nome, p.nome;

-- 2) cria a categoria "Ciclo" (só se ainda não existir) na empresa desses produtos
INSERT INTO categorias (empresa_id, nome, created_at, updated_at)
SELECT DISTINCT p.empresa_id, 'Ciclo', NOW(), NOW()
FROM produtos p
LEFT JOIN marcas m ON m.id = p.marca_id
WHERE (m.nome LIKE '%ciclo%' OR p.nome LIKE '%ciclo%')
  AND NOT EXISTS (SELECT 1 FROM categorias c2 WHERE c2.empresa_id = p.empresa_id AND c2.nome = 'Ciclo');

-- 3) move os produtos da Ciclo (que hoje estão em Árabes, Body Splash, Hidratantes…) para a categoria nova
UPDATE produtos p
LEFT JOIN marcas m ON m.id = p.marca_id
JOIN categorias c ON c.empresa_id = p.empresa_id AND c.nome = 'Ciclo'
SET p.categoria_id = c.id
WHERE m.nome LIKE '%ciclo%' OR p.nome LIKE '%ciclo%';

-- 4) confira: todos devem aparecer com a categoria "Ciclo"
SELECT c.nome AS categoria, COUNT(*) AS produtos
FROM produtos p JOIN categorias c ON c.id = p.categoria_id
WHERE c.nome = 'Ciclo'
GROUP BY c.nome;
