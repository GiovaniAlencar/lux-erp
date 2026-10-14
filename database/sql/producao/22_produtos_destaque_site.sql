-- Vitrine "Destaques" do site: o ERP escolhe quais produtos aparecem e em que ordem.
-- Compatível com migration: 2026_10_14_100000_add_destaque_site_to_produtos.php

ALTER TABLE `produtos`
  ADD COLUMN `destaque_site` tinyint(1) NOT NULL DEFAULT 0 AFTER `fiscal`,
  ADD COLUMN `destaque_ordem` int NOT NULL DEFAULT 0 AFTER `destaque_site`,
  ADD COLUMN `destaque_ate` date NULL DEFAULT NULL AFTER `destaque_ordem`,
  ADD INDEX `idx_produtos_destaque_site` (`destaque_site`, `destaque_ordem`);
