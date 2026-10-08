-- Produção: colunas para create pedido do site (luxerp)
-- Ver também: api-lux/sql/06-producao-orders-create-fix.sql

ALTER TABLE `ecommerce_orders` ADD COLUMN `expires_at` timestamp NULL DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `payment_status` varchar(30) DEFAULT 'pendente';
ALTER TABLE `ecommerce_orders` ADD COLUMN `venda_id` int(11) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `cancelled_reason` varchar(255) DEFAULT NULL;

ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_street` varchar(255) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_number` varchar(20) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_complement` varchar(255) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_neighborhood` varchar(120) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_city` varchar(120) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_state` varchar(2) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `delivery_zip_code` varchar(20) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `address_id` int(11) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `shipping_price` decimal(10,2) DEFAULT NULL;
ALTER TABLE `ecommerce_orders` ADD COLUMN `shipping_status` varchar(20) DEFAULT 'to_combine';

CREATE TABLE IF NOT EXISTS `ecommerce_stock_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `reserved_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product_status` (`product_id`, `status`),
  KEY `idx_expires` (`expires_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
