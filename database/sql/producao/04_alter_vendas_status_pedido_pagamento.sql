-- Status operacional e de pagamento na venda (lista de vendas)
-- Compatível com migration: 2026_04_08_230000_add_status_pedido_pagamento_to_vendas_table.php

ALTER TABLE `vendas`
  ADD COLUMN `status_pedido` VARCHAR(30) NOT NULL DEFAULT 'em_elaboracao' AFTER `estado_emissao`,
  ADD COLUMN `status_pagamento` VARCHAR(30) NOT NULL DEFAULT 'pendente' AFTER `status_pedido`;

UPDATE `vendas` SET `status_pedido` = 'finalizada' WHERE `estado_emissao` = 'aprovado';
UPDATE `vendas` SET `status_pedido` = 'cancelada' WHERE `estado_emissao` = 'cancelado';
UPDATE `vendas` SET `status_pedido` = 'em_elaboracao' WHERE `estado_emissao` IN ('novo', 'rejeitado');

UPDATE `vendas` SET `status_pagamento` = 'pago' WHERE `forma_pagamento` = 'a_vista';
UPDATE `vendas` SET `status_pagamento` = 'pendente' WHERE `forma_pagamento` = 'conta_crediario';
