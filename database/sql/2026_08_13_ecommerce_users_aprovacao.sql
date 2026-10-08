-- Colunas de aprovação/reprovação de usuários do site
-- Banco: luxerp (produção / local)

ALTER TABLE `ecommerce_users`
  ADD COLUMN `admin_notes` text NULL AFTER `status`,
  ADD COLUMN `approved_at` timestamp NULL DEFAULT NULL AFTER `admin_notes`,
  ADD COLUMN `approved_by` int(11) NULL DEFAULT NULL AFTER `approved_at`,
  ADD COLUMN `rejected_at` timestamp NULL DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `rejection_reason` varchar(255) NULL DEFAULT NULL AFTER `rejected_at`;
