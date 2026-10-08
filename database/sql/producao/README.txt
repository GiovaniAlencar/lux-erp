Scripts SQL para aplicar manualmente no banco de PRODUÇÃO (quando não usar php artisan migrate).

Ordem sugerida:
1) 01_alter_pedidos_status.sql
2) 02_create_pedido_auditorias.sql
3) 03_alter_alteracao_estoques_contexto.sql
4) 04_alter_vendas_status_pedido_pagamento.sql
5) 05_create_venda_auditorias.sql
6) 06_normalize_vendas_status_pedido_workflow.sql  (obrigatório se não rodou a migration 2026_04_09 que renomeia em_elaboracao → aguardando_confirmacao)
7) 07_alter_vendas_versao_pedido.sql
8) 08_create_rotas_entrega.sql
9) 09_alter_vendas_fechada_caixa.sql  (só se produção ainda não tem fechada_caixa)
10) 10_alter_rotas_entrega_motoboy_pago.sql
11) 15_create_promocoes.sql (módulo de promoções por produto)
12) 16_alter_ecommerce_order_items_promocao_id.sql (rastreia promoção usada em cada item do site; requer 15 aplicado antes)
13) 17_add_fiscal_produtos_item_vendas.sql (divisão fiscal / não fiscal da venda em duas contas)

Deploy completo (SQL + lista de arquivos FTP): ver DEPLOY_JUN2026.txt

Antes de executar: faça backup completo do banco.

Se alguma coluna ou tabela já existir, o MySQL retornará erro — remova só o trecho correspondente do script ou use IF NOT EXISTS conforme sua versão.
