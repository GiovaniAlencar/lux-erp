-- Alinha status_pedido ao workflow da aplicação (lista de vendas / workflow).
-- Compatível com migration: 2026_04_09_100000_venda_workflow_fechamento_caixa.php (parte de dados)
--
-- Rode DEPOIS de 04_alter_vendas_status_pedido_pagamento.sql (e dos demais scripts anteriores).
-- O script 04 deixa 'em_elaboracao' e 'finalizada'; o código PHP usa 'aguardando_confirmacao' e 'confirmado'.

UPDATE `vendas` SET `status_pedido` = 'aguardando_confirmacao' WHERE `status_pedido` = 'em_elaboracao';
UPDATE `vendas` SET `status_pedido` = 'confirmado' WHERE `status_pedido` = 'finalizada';

-- Opcional: novas linhas que não receberem status no INSERT passam a usar o mesmo default do app.
-- Descomente se quiser alinhar o DEFAULT da coluna ao código:
-- ALTER TABLE `vendas` MODIFY COLUMN `status_pedido` VARCHAR(30) NOT NULL DEFAULT 'aguardando_confirmacao';
