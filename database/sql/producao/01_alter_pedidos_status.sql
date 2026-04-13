-- Status operacional e de pagamento da comanda/pedido (tabela pedidos)
-- Compatível com migrations: 2026_04_08_180700_add_status_pagamento_to_pedidos_table.php

ALTER TABLE `pedidos`
  ADD COLUMN `status_pedido` VARCHAR(30) NOT NULL DEFAULT 'aberto' AFTER `status`,
  ADD COLUMN `status_pagamento` VARCHAR(30) NOT NULL DEFAULT 'pendente' AFTER `status_pedido`;
