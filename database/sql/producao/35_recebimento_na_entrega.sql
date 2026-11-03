-- Forma de recebimento de cada parcela da venda: já pago antes ou cobrar na entrega.
-- O app do motoboy passa a cobrar só o que está marcado "na entrega".
-- NULL = venda antiga (sem a informação): continua cobrando o que falta, como antes.

ALTER TABLE `conta_recebers`
  ADD COLUMN `na_entrega` TINYINT(1) NULL DEFAULT NULL AFTER `tipo_pagamento`;
