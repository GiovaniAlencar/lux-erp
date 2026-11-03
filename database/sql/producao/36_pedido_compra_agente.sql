-- Pedido de compra: % do agente (comissão sobre o valor do fornecedor, antes da conversão do dólar).
-- Ex.: fornecedor US$ 1.000 + 5% do agente = US$ 1.050 a pagar. Vazio = sem agente. Requer 33.

ALTER TABLE `compra_pedidos`
  ADD COLUMN `pct_agente` decimal(6,2) NULL AFTER `total_moeda`;
