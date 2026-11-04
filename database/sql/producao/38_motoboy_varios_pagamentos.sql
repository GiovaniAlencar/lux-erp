-- App do motoboy: mais de um pagamento na mesma entrega (ex.: parte no PIX e o resto no cartão)
-- e correção depois de concluída (desfazer um pagamento e lançar outro). Requer 32.
-- Cada item guarda a lista dos comprovantes que o motoboy registrou: [{comprovante_id, forma, valor, liquido, bandeira, parcelas, em}].

ALTER TABLE `rota_entrega_itens`
  ADD COLUMN `recebimentos` json NULL AFTER `recebido_comprovante`;
