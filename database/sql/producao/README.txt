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
14) 18_create_notas_fiscais.sql (emissão de NF-e da parte fiscal pelo ERP)
15) 19_estoque_fiscal.sql (saldo de estoque com nota; requer 17 aplicado antes)
16) 20_custo_fiscal.sql (custo médio das unidades com nota; requer 19)
17) 21_site_cadastro_documento_notificacoes.sql (CPF/CNPJ e consentimento no cadastro do site + fila de notificações)
18) 22_produtos_destaque_site.sql (vitrine "Destaques" do site, escolhida no ERP)
19) 23_site_esgotados_avise_me.sql (esgotados no site + "Avise-me quando chegar"; requer 21 e 22)
20) 24_site_recuperar_senha.sql ("Esqueci minha senha" do site; tokens guardados só como hash)
21) 25_produtos_reposto_em.sql (vitrine "Reposições" do site; requer 23)
22) 26_financeiro_contas_extrato.sql (contas com saldo/extrato, pagamentos parciais com comprovante)
23) 27_venda_comprovantes.sql (vendedor anexa comprovante de pagamento na venda)
24) 28_comprovante_venda_financeiro.sql (comprovante da venda lança a entrada no extrato da conta; requer 26 e 27)
25) 29_contas_pagar_despesa.sql (marca se a conta a pagar entra como despesa no fechamento; requer 26)
26) 30_extrato_pendencia_comprovante.sql (comprovante excluído deixa aviso no extrato para estornar ou vincular a outra venda; requer 26 e 28)
27) 31_categoria_ciclo.sql (categoria "Ciclo" para a linha nacional e move os produtos da marca; só dados, sem mudar tabelas)
28) 32_motoboy_recebimentos_saldo.sql (app do motoboy: link da rota, foto do comprovante, coordenadas; saldo do cliente; requer 26, 27 e 28)
29) 33_pedidos_compra.sql (pedidos de compra em dólar/real: pagamentos com cotação, fretes, entrada e pendências; requer 26)
30) 34_fechamento_mes.sql (fechamento do mês: divisão entre sócios, retiradas e a foto do fechamento; requer 26, 29 e 33)
31) 35_recebimento_na_entrega.sql (cada pagamento da venda marca se já foi pago ou se o motoboy cobra na entrega)
32) 36_pedido_compra_agente.sql (% do agente no pedido de compra, antes da conversão do dólar; requer 33)
33) 37_crm.sql (CRM LUX: campanhas, fila de contatos, histórico, bloqueios; nesta fase não envia nada)
34) 38_motoboy_varios_pagamentos.sql (motoboy: mais de um pagamento por entrega e correção depois de concluída; requer 32)
35) 39_motoboys_acertos.sql (motoboys cadastrados e acerto do frete: conta a pagar semanal ou no dia, extras como despesa; requer 26 e 29)
36) 40_categoria_nacionais.sql (categoria "Ciclo" vira "Nacionais" e recebe a Paris Elysees; só dados, sem mudar tabelas)

Deploy completo (SQL + lista de arquivos FTP): ver DEPLOY_JUN2026.txt

Antes de executar: faça backup completo do banco.

Se alguma coluna ou tabela já existir, o MySQL retornará erro — remova só o trecho correspondente do script ou use IF NOT EXISTS conforme sua versão.
