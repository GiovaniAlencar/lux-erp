Scripts SQL para aplicar manualmente no banco de PRODUÇÃO (quando não usar php artisan migrate).

Ordem sugerida:
1) 01_alter_pedidos_status.sql
2) 02_create_pedido_auditorias.sql
3) 03_alter_alteracao_estoques_contexto.sql
4) 04_alter_vendas_status_pedido_pagamento.sql
5) 05_create_venda_auditorias.sql
6) 06_normalize_vendas_status_pedido_workflow.sql  (obrigatório se não rodou a migration 2026_04_09 que renomeia em_elaboracao → aguardando_confirmacao)

Antes de executar: faça backup completo do banco.

Se alguma coluna ou tabela já existir, o MySQL retornará erro — remova só o trecho correspondente do script ou use IF NOT EXISTS conforme sua versão.
