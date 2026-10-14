-- Vitrine "Reposições" do site: quando o produto voltou ao estoque (de zero para positivo).
-- Preenchido sozinho pelo ERP a cada reposição; o site mostra os dos últimos 30 dias.
ALTER TABLE `produtos`
  ADD COLUMN `reposto_em` datetime NULL DEFAULT NULL AFTER `ocultar_site`,
  ADD INDEX `idx_produtos_reposto_em` (`reposto_em`);

-- Opcional: já preenche com as reposições dos últimos 30 dias registradas no histórico
-- de movimentação, para a vitrine não começar vazia.
UPDATE `produtos` p
JOIN (
  SELECT produto_id, MAX(created_at) AS quando
  FROM `alteracao_estoques`
  WHERE estoque_anterior <= 0 AND estoque_novo > 0
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY produto_id
) a ON a.produto_id = p.id
SET p.reposto_em = a.quando;
