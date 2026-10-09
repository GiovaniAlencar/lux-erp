-- Custo fiscal (média só das unidades com nota) + custo unitário nos movimentos do estoque fiscal.
-- Compatível com migration: 2026_10_09_130000_add_custo_fiscal.php. Requer 19 aplicado antes.

ALTER TABLE `produtos`
  ADD COLUMN `custo_fiscal` decimal(14,4) NOT NULL DEFAULT 0 AFTER `estoque_fiscal`;

ALTER TABLE `estoque_fiscal_movimentos`
  ADD COLUMN `custo_unitario` decimal(14,4) NULL AFTER `saldo_apos`;
