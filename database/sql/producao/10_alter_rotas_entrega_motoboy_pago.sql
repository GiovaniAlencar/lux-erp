-- Controle: empresa já pagou o valor total da rota ao motoboy?
-- Compatível com migration: 2026_06_10_100000_add_motoboy_pago_to_rotas_entrega_table.php

ALTER TABLE `rotas_entrega`
  ADD COLUMN `motoboy_pago` tinyint(1) NOT NULL DEFAULT 0 AFTER `motoboy_nome`,
  ADD COLUMN `motoboy_pago_em` timestamp NULL DEFAULT NULL AFTER `motoboy_pago`,
  ADD COLUMN `motoboy_pago_usuario_id` int unsigned DEFAULT NULL AFTER `motoboy_pago_em`;

ALTER TABLE `rotas_entrega`
  ADD CONSTRAINT `rotas_entrega_motoboy_pago_usuario_id_foreign`
  FOREIGN KEY (`motoboy_pago_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
