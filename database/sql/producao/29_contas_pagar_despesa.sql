-- Contas a pagar: marcar se ENTRA COMO DESPESA no fechamento do mês (lucro − despesas).
-- Ex.: o frete pago ao motoboy não é despesa quando é cobrado do cliente no pedido (repasse);
--      compras viram estoque/custo do produto; o DIFAL já entra no custo pela entrada do XML.
--      Já taxas de pagamento, perfumes quebrados, mídias, juros etc. são despesa.
-- Cada categoria tem o seu padrão, e cada conta pode trocar na hora de lançar. Requer 26.

ALTER TABLE `categoria_contas`
  ADD COLUMN `despesa` tinyint(1) NOT NULL DEFAULT 1 AFTER `tipo`;

ALTER TABLE `conta_pagars`
  ADD COLUMN `despesa` tinyint(1) NOT NULL DEFAULT 1 AFTER `categoria_id`;

-- padrões da LUX: não entram como despesa
UPDATE `categoria_contas` SET `despesa` = 0
 WHERE `tipo` = 'pagar' AND `nome` IN ('Entregas', 'Compras', 'Impostos (DIFAL)');

-- contas já lançadas seguem a categoria
UPDATE `conta_pagars` c
  JOIN `categoria_contas` k ON k.id = c.categoria_id
   SET c.`despesa` = k.`despesa`;
