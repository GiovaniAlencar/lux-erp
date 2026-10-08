-- Rastreia qual promoção (se alguma) foi usada em cada item de pedido do site,
-- para permitir incrementar promocoes.quantidade_utilizada corretamente na confirmação.
-- Compatível com migration:
--   2026_08_26_110000_add_promocao_id_to_ecommerce_order_items.php
-- Pré-requisito: 15_create_promocoes.sql já aplicado.

ALTER TABLE `ecommerce_order_items`
  ADD COLUMN `promocao_id` int unsigned DEFAULT NULL AFTER `total_price`,
  ADD CONSTRAINT `ecommerce_order_items_promocao_id_foreign`
    FOREIGN KEY (`promocao_id`) REFERENCES `promocoes` (`id`) ON DELETE SET NULL;
