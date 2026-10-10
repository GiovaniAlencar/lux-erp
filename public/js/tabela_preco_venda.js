/**
 * Tabelas de preço por categoria (Árabes / Miniaturas) — venda.
 */
(function (window) {
    'use strict';

    const GRUPOS = {
        arabes: {
            label: 'Árabes / Francês',
            faixas: [
                { min: 1, max: 9, tier: 'normal' },
                { min: 10, max: 19, tier: 'atacado_1' },
                { min: 20, max: null, tier: 'atacado_2' },
            ],
            metas: [
                { qty: 10, tier: 'atacado_1' },
                { qty: 20, tier: 'atacado_2' },
            ],
        },
        miniaturas: {
            label: 'Miniaturas',
            faixas: [
                { min: 1, max: 5, tier: 'normal' },
                { min: 6, max: 11, tier: 'atacado_1' },
                { min: 12, max: null, tier: 'atacado_2' },
            ],
            metas: [
                { qty: 6, tier: 'atacado_1' },
                { qty: 12, tier: 'atacado_2' },
            ],
        },
    };

    const TIER_LABEL = {
        normal: 'Normal',
        atacado_1: 'Atacado 1',
        atacado_2: 'Atacado 2',
    };

    const TIER_BADGE = {
        normal: 'badge-tabela-normal',
        atacado_1: 'badge-tabela-atacado-1',
        atacado_2: 'badge-tabela-atacado-2',
    };

    const modos = {};
    let ultimoProdutoSelecionado = null;
    let aplicarTimer = null;

    function config() {
        return window.PRECO_CATEGORIA_CONFIG || {};
    }

    function sincronizarModosDoSelect() {
        $('.select-modo-preco').each(function () {
            const grupo = $(this).data('grupo');
            if (grupo) {
                modos[grupo] = $(this).val() || 'auto';
            }
        });
    }

    function initModos() {
        const cfg = config();
        Object.keys(cfg).forEach(function (grupo) {
            const $sel = $('.select-modo-preco[data-grupo="' + grupo + '"]');
            const $hidden = grupo === 'arabes' ? $('#inp-modo-preco-arabes') : $('#inp-modo-preco-miniaturas');
            let modo = ($hidden.val() || $sel.val() || 'auto');
            if ($sel.length) {
                $sel.val(modo);
            }
            if ($hidden.length) {
                $hidden.val(modo);
            }
            modos[grupo] = modo || 'auto';
        });
    }

    function grupoPorCategoriaId(catId) {
        const id = parseInt(catId, 10);
        if (!id) return '';
        const cfg = config();
        for (let i = 0; i < Object.keys(cfg).length; i++) {
            const key = Object.keys(cfg)[i];
            const ids = cfg[key].categoria_ids || [cfg[key].categoria_id];
            for (let j = 0; j < ids.length; j++) {
                if (parseInt(ids[j], 10) === id) {
                    return key;
                }
            }
        }
        return '';
    }

    function grupoDaLinha($tr) {
        let grupo = ($tr.attr('data-grupo-preco') || '').trim();
        if (grupo) {
            return grupo;
        }
        grupo = grupoPorCategoriaId($tr.attr('data-categoria-id'));
        if (grupo) {
            $tr.attr('data-grupo-preco', grupo);
        }
        return grupo;
    }

    function tierAutomatico(grupo, qtd) {
        const def = GRUPOS[grupo];
        if (!def) return 'normal';
        const q = Math.floor(qtd + 0.0001);
        for (let i = 0; i < def.faixas.length; i++) {
            const f = def.faixas[i];
            if (q >= f.min && (f.max === null || q <= f.max)) {
                return f.tier;
            }
        }
        return 'normal';
    }

    function tierPorModo(grupo, qtdTotal) {
        const modo = modos[grupo] || 'auto';
        if (modo === 'normal') return 'normal';
        if (modo === 'atacado_1') return 'atacado_1';
        if (modo === 'atacado_2') return 'atacado_2';
        return tierAutomatico(grupo, qtdTotal);
    }

    function precoPorTier($row, tier) {
        const normal = parseFloat($row.find('.inp-preco-normal').val() || '0');
        const p1 = parseFloat($row.find('.inp-preco-atacado-1').val() || '0') || normal;
        const p2 = parseFloat($row.find('.inp-preco-atacado-2').val() || '0') || p1;
        if (tier === 'atacado_2') return p2;
        if (tier === 'atacado_1') return p1;
        return normal;
    }

    function coletarLinhas() {
        const linhas = [];
        const $rows = $('.table-itens tbody tr.linha-item-venda');
        const $alvo = $rows.length
            ? $rows
            : $('.table-itens tbody tr').filter(function () {
                return $(this).find('.qtd_row').length && !$(this).hasClass('empty-state');
            });

        $alvo.each(function () {
            const $tr = $(this);
            const grupo = grupoDaLinha($tr);
            if (!grupo) return;
            const qtd = convertMoedaToFloat($tr.find('.qtd_row').val() || '0');
            linhas.push({ $tr: $tr, grupo: grupo, qtd: qtd });
        });
        return linhas;
    }

    function agendarAplicar(delayMs) {
        clearTimeout(aplicarTimer);
        aplicarTimer = setTimeout(aplicar, typeof delayMs === 'number' ? delayMs : 80);
    }

    function somarPorGrupo(linhas) {
        const totais = {};
        linhas.forEach(function (l) {
            totais[l.grupo] = (totais[l.grupo] || 0) + l.qtd;
        });
        return totais;
    }

    function atualizarBadgeLinha($tr, tier) {
        const label = TIER_LABEL[tier] || 'Normal';
        const cls = TIER_BADGE[tier] || TIER_BADGE.normal;
        let $info = $tr.find('.linha-tabela-preco-info');
        if (!$info.length) {
            $info = $('<div class="mt-1 small linha-tabela-preco-info"></div>');
            $tr.find('td').eq(1).append($info);
        }
        const novoHtml = '<span class="badge rounded-pill ' + cls + ' badge-tabela-aplicada">' + label + '</span>' +
            '<span class="text-muted ms-1">Tabela: ' + label + '</span>';
        // só reescreve se mudou: reescrever sempre disparava o observador da tabela em loop (a cada 80 ms)
        if ($info.html() !== novoHtml) {
            $info.html(novoHtml);
        }
    }

    function infoPainel(grupo, qtd, modo, tier) {
        const def = GRUPOS[grupo];
        const labelTier = TIER_LABEL[tier] || 'Normal';
        let mensagem = '';
        let progressoAtual = 0;
        let progressoMeta = 0;

        if (modo !== 'auto') {
            mensagem = labelTier + ' (manual)';
        } else if (tier === 'atacado_2') {
            mensagem = 'Atacado 2 ativo';
            const ultima = def.metas[def.metas.length - 1];
            progressoAtual = qtd;
            progressoMeta = ultima ? ultima.qty : qtd;
        } else {
            let proxima = null;
            def.metas.forEach(function (m) {
                if (qtd < m.qty && (!proxima || m.qty < proxima.qty)) {
                    proxima = m;
                }
            });
            if (proxima) {
                const faltam = Math.max(0, proxima.qty - qtd);
                const proxLabel = TIER_LABEL[proxima.tier] || proxima.tier;
                if (tier !== 'normal') {
                    mensagem = labelTier + ' ativo. Faltam ' + faltam + ' unidades para liberar ' + proxLabel;
                } else {
                    mensagem = 'Faltam ' + faltam + ' unidades para liberar ' + proxLabel;
                }
                progressoAtual = qtd;
                progressoMeta = proxima.qty;
            } else {
                mensagem = labelTier + ' ativo';
            }
        }

        return { mensagem: mensagem, progressoAtual: progressoAtual, progressoMeta: progressoMeta, labelTier: labelTier };
    }

    function atualizarPainel(grupo, qtd, tier) {
        const modo = modos[grupo] || 'auto';
        const info = infoPainel(grupo, qtd, modo, tier);
        const pct = info.progressoMeta > 0
            ? Math.min(100, Math.round((info.progressoAtual / info.progressoMeta) * 100))
            : (qtd > 0 ? 100 : 0);

        $('.qtd-grupo[data-grupo="' + grupo + '"]').text(String(Math.floor(qtd)));
        $('.texto-progresso[data-grupo="' + grupo + '"]').text(info.mensagem);
        $('.barra-progresso[data-grupo="' + grupo + '"]').css('width', pct + '%');

        const $fracao = $('.fracao-progresso[data-grupo="' + grupo + '"]');
        if (modo === 'auto' && info.progressoMeta > 0 && qtd > 0) {
            $fracao.text(Math.floor(info.progressoAtual) + '/' + info.progressoMeta);
        } else {
            $fracao.text('');
        }

        const cls = TIER_BADGE[tier] || TIER_BADGE.normal;
        $('.badge-tier-ativo[data-grupo="' + grupo + '"]')
            .attr('class', 'badge rounded-pill badge-tier-ativo ' + cls)
            .text(info.labelTier);

        const $hidden = grupo === 'arabes' ? $('#inp-modo-preco-arabes') : $('#inp-modo-preco-miniaturas');
        if ($hidden.length) $hidden.val(modo);
    }

    function aplicar() {
        const cfg = config();
        if (!Object.keys(cfg).length) return;

        sincronizarModosDoSelect();

        const linhas = coletarLinhas();
        const totais = somarPorGrupo(linhas);
        const tierPorGrupo = {};

        Object.keys(cfg).forEach(function (grupo) {
            const qtd = totais[grupo] || 0;
            const tier = tierPorModo(grupo, qtd);
            tierPorGrupo[grupo] = tier;
            atualizarPainel(grupo, Math.floor(qtd), tier);
        });

        linhas.forEach(function (l) {
            const tier = tierPorGrupo[l.grupo] || 'normal';
            const preco = precoPorTier(l.$tr, tier);
            const sub = preco * l.qtd;
            const vPreco = convertFloatToMoeda(preco), vSub = convertFloatToMoeda(sub);
            const $vu = l.$tr.find('.value_unit_row'), $st = l.$tr.find('.subtotal-item');
            if ($vu.val() !== vPreco) $vu.val(vPreco);
            if ($st.val() !== vSub) $st.val(vSub);
            atualizarBadgeLinha(l.$tr, tier);
        });

        if (typeof calcTotal === 'function') {
            calcTotal();
        }
    }

    function previewPrecoEntrada(produto, qtdLinhaOpcional) {
        if (!produto) return;

        if (!produto.grupo_preco) {
            const g = grupoPorCategoriaId(produto.categoria_id);
            if (g) produto.grupo_preco = g;
        }

        if (!produto.grupo_preco) {
            $('#inp-valor_unitario').val(convertFloatToMoeda(produto.valor_venda || 0));
            const q = qtdLinhaOpcional !== undefined
                ? convertMoedaToFloat(qtdLinhaOpcional)
                : convertMoedaToFloat($('#inp-quantidade').val() || '1');
            $('#inp-subtotal').val(convertFloatToMoeda((produto.valor_venda || 0) * q));
            return;
        }

        sincronizarModosDoSelect();

        const linhas = coletarLinhas();
        const totais = somarPorGrupo(linhas);
        const qtdAdd = qtdLinhaOpcional !== undefined
            ? convertMoedaToFloat(qtdLinhaOpcional)
            : convertMoedaToFloat($('#inp-quantidade').val() || '1');
        const qtdAtual = totais[produto.grupo_preco] || 0;
        const qtdNova = qtdAtual + (qtdAdd > 0 ? qtdAdd : 1);
        const tier = tierPorModo(produto.grupo_preco, qtdNova);

        let preco = parseFloat(produto.valor_venda || 0);
        if (tier === 'atacado_1') preco = parseFloat(produto.preco_atacado_1 || preco);
        if (tier === 'atacado_2') preco = parseFloat(produto.preco_atacado_2 || preco);

        $('#inp-valor_unitario').val(convertFloatToMoeda(preco));
        $('#inp-subtotal').val(convertFloatToMoeda(preco * (qtdAdd > 0 ? qtdAdd : 1)));
    }

    function bindEvents() {
        $(document).on('change', '.select-modo-preco', function () {
            const grupo = $(this).data('grupo');
            modos[grupo] = $(this).val() || 'auto';
            aplicar();
        });

        $(document).on('input change', '#inp-quantidade', function () {
            if (ultimoProdutoSelecionado) {
                previewPrecoEntrada(ultimoProdutoSelecionado, $(this).val());
            }
        });

        $(document).on('input change', '.table-itens .qtd_row, .table-itens .value_unit_row', function () {
            agendarAplicar(80);
        });

        $(document).on('venda:itens-alterados', function () {
            agendarAplicar(80);
        });

        $(document).ajaxSuccess(function (_evt, _xhr, settings) {
            const url = settings && settings.url ? String(settings.url) : '';
            if (url.indexOf('linhaProdutoVenda') !== -1) {
                agendarAplicar(120);
            }
        });
    }

    function observarTabelaItens() {
        const tbody = document.querySelector('.table-itens tbody');
        if (!tbody) {
            return false;
        }
        if (tbody._tabelaPrecoObserver) {
            return true;
        }

        const obs = new MutationObserver(function () {
            agendarAplicar(80);
        });
        obs.observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['value'] });
        tbody._tabelaPrecoObserver = obs;
        return true;
    }

    function init() {
        initModos();
        bindEvents();
        if (!observarTabelaItens()) {
            setTimeout(function () {
                observarTabelaItens();
                agendarAplicar(100);
            }, 500);
        }
        sincronizarModosDoSelect();
        agendarAplicar(300);
    }

    window.TabelaPrecoVenda = {
        aplicar: aplicar,
        previewPrecoEntrada: previewPrecoEntrada,
        setUltimoProduto: function (p) { ultimoProdutoSelecionado = p; },
        init: init,
    };

    $(function () {
        if (Object.keys(config()).length) {
            init();
        }
    });
})(window);
