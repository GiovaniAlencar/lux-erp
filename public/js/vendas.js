var REFERENCIASNFE = [];
var TOTAL = 0;
var SENHADESBLOQUEADA = false

$('#remover_parcelas').click(() => {
    $('.table-payment tbody').html('')
    $('.sum-payment').html('')

})

$('#btn-store-produto').click(() => {
    let valid = validaCamposModal("#modal-produto")
    if (valid.length > 0) {
        let msg = ""
        valid.map((x) => {
            msg += x + "\n"
        })
        swal("Ops, erro no formulário", msg, "error")
    } else {
        console.clear()

        let data = {}
        $(".modal input, .modal select").each(function () {

            let indice = $(this).attr('id')
            indice = indice.substring(4, indice.length)
            data[indice] = $(this).val()
        });
        data['empresa_id'] = $('#empresa_id').val()

        $.post(path_url + 'api/produtos/store', data)
        .done((success) => {
            swal("Sucesso", "Produto cadastrado!", "success")
            .then(() => {
                var newOption = new Option(success.nome, success.id, false, false);
                $('#inp-produto_id').append(newOption).trigger('change');
                $('#modal-produto').modal('hide')
            })

        }).fail((err) => {
            swal("Ops", "Algo deu errado ao salvar produto!", "error")
        })
    }
})


$(function () {
    setTimeout(() => {
        validateButtonSave();
        calcTotal()
    }, 300)

    // Padroniza campos com classe form-control na tela de venda e modais relacionados
    try {
        const $scopes = $('.vendas-create-modern, .modal');
        $scopes.find('input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=reset])')
            .addClass('form-control');
        $scopes.find('textarea').addClass('form-control');
        $scopes.find('select').removeClass('form-select').addClass('form-control');
    } catch(e){}

    $('.modal .select2').each(function () {
        let id = $(this).prop('id')

        if (id == 'inp-categoria_id') {
            $(this).select2({
                dropdownParent: $(this).parent(),
                theme: 'bootstrap4',
            });
        }
        /*  select de marcas não estava funcionando, então coloquei mais essa condição para
        teste */

        else if (id == 'inp-marca_id') {
            $(this).select2({
                dropdownParent: $(this).parent(),
                theme: 'bootstrap4',
            });
        }

        else if (id == 'inp-sub_categoria_id') {

            $(this).select2({
                minimumInputLength: 2,
                language: "pt-BR",
                placeholder: "Digite para buscar a subcategoria",
                width: "100%",
                dropdownParent: $(this).parent(),
                theme: "bootstrap4",
                ajax: {
                    cache: true,
                    url: path_url + "api/categorias/buscarSubCategoria",
                    dataType: "json",
                    data: function (params) {
                        console.clear();

                        let empresa_id = $("#empresa_id").val();
                        let categoria_id = $("#inp-categoria_id").val();

                        if (categoria_id) {
                            var query = {
                                pesquisa: params.term,
                                empresa_id: empresa_id,
                                categoria_id: categoria_id,
                            };
                            return query;
                        } else {
                            swal("Erro", "Selecione uma categoria!", "warning");
                        }
                    },
                    processResults: function (response) {
                        var results = [];

                        $.each(response, function (i, v) {
                            var o = {};
                            o.id = v.id;

                            o.text = v.nome;
                            o.value = v.id;
                            results.push(o);
                        });
                        return {
                            results: results,
                        };
                    },
                },

            });
        } else {
            $(this).select2({
                dropdownParent: $(this).parent(),
                theme: 'bootstrap4',
            });
        }
    })

    setTimeout(() => {
        $("#inp-produto_id").change(() => {
            let product_id = $("#inp-produto_id").val()
            if (product_id) {
                $.get(path_url + "api/produtos/find/" + product_id)
                .done((e) => {
                    $('#inp-quantidade').val('1,00')
                    $('#inp-valor_unitario').val(convertFloatToMoeda(e.valor_venda))
                    $('#inp-subtotal').val(convertFloatToMoeda(e.valor_venda))
                })
                .fail((e) => {
                    console.log(e)
                })
            }

        })
    }, 100)

    $('body').on('blur', '.value_unit', function () {
        let qtd = $('#inp-quantidade').val();
        let value_unit = $(this).val();
        value_unit = convertMoedaToFloat(value_unit)
        qtd = convertMoedaToFloat(qtd)
        $('#inp-subtotal').val(convertFloatToMoeda(qtd * value_unit))
    })

    $('#inp-cliente_id').change(() => {
        validateButtonSave()
    })
})

// Editar endereço do cliente (modal)
$(document).on('click', '#btn-open-edit-endereco', function(){
    const clienteId = $('#inp-cliente_id').val();
    if(!clienteId){
        swal("Atenção", "Selecione um cliente antes de editar o endereço!", "warning");
        return;
    }
    $('#modal-endereco-cliente').modal('show');
    // inicializa select2 do modal (cidade)
    setTimeout(() => {
        const $cidade = $('#modal-endereco-cliente #inp-cidade_id');
        if ($cidade.data('select2')) {
            // já inicializado
        } else {
            $cidade.select2({
                minimumInputLength: 2,
                language: "pt-BR",
                placeholder: "Digite para buscar a cidade",
                width: "100%",
                theme: 'bootstrap4',
                dropdownParent: $cidade.parent(),
                ajax: {
                    cache: true,
                    url: path_url + 'api/buscaCidades',
                    dataType: "json",
                    data: function(params) {
                        return { pesquisa: params.term };
                    },
                    processResults: function(response) {
                        const results = [];
                        $.each(response, function(i, v) {
                            results.push({ id: v.id, text: v.nome + "(" + v.uf + ")", value: v.id });
                        });
                        return { results: results };
                    }
                }
            });
        }
    }, 150);

    // busca dados do cliente e preenche
    $.get(path_url + 'api/cliente/find/' + clienteId)
        .done((res) => {
            $('#modal-endereco-cliente #inp-cep').val(res.cep || '');
            $('#modal-endereco-cliente #inp-rua').val(res.rua || '');
            $('#modal-endereco-cliente #inp-numero').val(res.numero || '');
            $('#modal-endereco-cliente #inp-bairro').val(res.bairro || '');
            $('#modal-endereco-cliente #inp-complemento').val(res.complemento || '');
            if (res.cidade) {
                const opt = new Option(res.cidade.nome + " (" + res.cidade.uf + ")", res.cidade.id, true, true);
                $('#modal-endereco-cliente #inp-cidade_id').append(opt).trigger('change');
            } else {
                $('#modal-endereco-cliente #inp-cidade_id').val(null).trigger('change');
            }
        })
        .fail((err) => {
            console.log(err);
            swal("Erro", "Não foi possível carregar o endereço do cliente.", "error");
        });
});

// Salvar endereço editado
$(document).on('click', '#btn-update-endereco', function(){
    const clienteId = $('#inp-cliente_id').val();
    if(!clienteId){
        swal("Atenção", "Selecione um cliente!", "warning");
        return;
    }
    const data = {
        cep: $('#modal-endereco-cliente #inp-cep').val(),
        rua: $('#modal-endereco-cliente #inp-rua').val(),
        numero: $('#modal-endereco-cliente #inp-numero').val(),
        bairro: $('#modal-endereco-cliente #inp-bairro').val(),
        complemento: $('#modal-endereco-cliente #inp-complemento').val(),
        cidade_id: $('#modal-endereco-cliente #inp-cidade_id').val()
    };
    $.post(path_url + 'api/cliente/updateEndereco/' + clienteId, data)
        .done((res) => {
            swal("Sucesso", "Endereço atualizado!", "success")
                .then(() => { $('#modal-endereco-cliente').modal('hide'); });
        })
        .fail((err) => {
            console.log(err);
            swal("Erro", "Falha ao atualizar endereço.", "error");
        });
});

// Limpar campos ao alterar CEP no modal de endereço e aplicar ViaCEP de forma escopada
$(document).on('input', '#modal-endereco-cliente #inp-cep', function(){
    const $ctx = $('#modal-endereco-cliente');
    // sempre limpa enquanto digita
    $ctx.find('#inp-rua').val('');
    $ctx.find('#inp-numero').val('');
    $ctx.find('#inp-bairro').val('');
    $ctx.find('#inp-complemento').val('');
    // limpa cidade (mantém select2)
    $ctx.find('#inp-cidade_id').val(null).trigger('change');
});

$(document).on('blur', '#modal-endereco-cliente #inp-cep', function(){
    let cep = $(this).val() ? $(this).val().replace(/[^0-9]/g, '') : '';
    const $ctx = $('#modal-endereco-cliente');
    if(cep.length !== 8) return;
    const url = "https://viacep.com.br/ws/" + cep + "/json";
    $.get(url)
        .done((res) => {
            if(res.erro) return;
            $ctx.find('#inp-rua').val(res.logradouro || '');
            $ctx.find('#inp-bairro').val(res.bairro || '');
            if(res.ibge){
                // usa versão escopada declarada em client.js
                if (typeof findCidadeIn === 'function') {
                    findCidadeIn($ctx, res.ibge);
                }
            }
        })
        .fail((err) => {
            console.log('Erro ViaCEP modal endereço', err);
        });
});

// Editar perfil do cliente (modal)
$(document).on('click', '#btn-open-edit-perfil', function(){
    const clienteId = $('#inp-cliente_id').val();
    if(!clienteId){
        swal("Atenção", "Selecione um cliente antes de editar o perfil!", "warning");
        return;
    }
    $('#modal-perfil-cliente').modal('show');
    // busca dados do cliente e preenche
    $.get(path_url + 'api/cliente/find/' + clienteId)
        .done((res) => {
            $('#modal-perfil-cliente #inp-razao_social').val(res.razao_social || '');
            $('#modal-perfil-cliente #inp-celular').val(res.celular || '');
            $('#modal-perfil-cliente #inp-email').val(res.email || '');
        })
        .fail((err) => {
            console.log(err);
            swal("Erro", "Não foi possível carregar o perfil do cliente.", "error");
        });
});

// Salvar perfil editado
$(document).on('click', '#btn-update-perfil', function(){
    const clienteId = $('#inp-cliente_id').val();
    if(!clienteId){
        swal("Atenção", "Selecione um cliente!", "warning");
        return;
    }
    const data = {
        razao_social: $('#modal-perfil-cliente #inp-razao_social').val(),
        celular: $('#modal-perfil-cliente #inp-celular').val(),
        email: $('#modal-perfil-cliente #inp-email').val()
    };
    $.post(path_url + 'api/cliente/updatePerfil/' + clienteId, data)
        .done((res) => {
            swal("Sucesso", "Perfil atualizado!", "success")
                .then(() => { $('#modal-perfil-cliente').modal('hide'); });
        })
        .fail((err) => {
            console.log(err);
            swal("Erro", "Falha ao atualizar perfil.", "error");
        });
});

var rand = null
function editItem(rand){
    this.rand = rand
    let valor_unitario = $('.tr_'+rand).find('.value_unit_row').val()
    let qtd = $('.tr_'+rand).find('.qtd_row').val()
    let x_pedido = $('.tr_'+rand).find('.x_pedido_row').val()
    let num_item_pedido = $('.tr_'+rand).find('.num_item_pedido_row').val()
    $('#modal-edit_item').modal('show')

    $('#inp-quantidade_modal').val(qtd)
    $('#inp-valor_modal').val(valor_unitario)
    $('#x_pedido_row').val(x_pedido)
    $('#num_item_pedido_row').val(num_item_pedido)
}

function salvarItem(){

    let valor_unitario = $('#inp-valor_modal').val()
    let qtd = $('#inp-quantidade_modal').val()

    let x_pedido = $('#inp-x_pedido').val()
    let num_item_pedido = $('#inp-num_item_pedido').val()

    $('.tr_'+this.rand).find('.value_unit_row').val(valor_unitario)
    $('.tr_'+this.rand).find('.qtd_row').val(qtd)

    $('.tr_'+this.rand).find('.x_pedido_row').val(x_pedido)
    $('.tr_'+this.rand).find('.num_item_pedido_row').val(num_item_pedido)


    valor_unitario = convertMoedaToFloat(valor_unitario)
    qtd = convertMoedaToFloat(qtd)
    $('.tr_'+this.rand).find('.subtotal-item').val(convertFloatToMoeda(qtd*valor_unitario))

    $('#modal-edit_item').modal('hide')
    // atualiza totais e estados dependentes
    if (typeof calcTotal === 'function') { 
        calcTotal(); 
        // atualiza sugestão de forma de pagamento/parcelas baseada no total
        if (typeof formasPagamento === 'function') {
            setTimeout(() => { formasPagamento(); }, 120);
        }
    }
    // dispara eventos nativos para atualizar resumos (create e edit)
    try {
        const $row = $('.tr_'+this.rand);
        const elQtd = $row.find('.qtd_row').get(0);
        const elUnit = $row.find('.value_unit_row').get(0);
        const elSub = $row.find('.subtotal-item').get(0);
        const ev = new Event('input', { bubbles: true });
        if (elQtd) elQtd.dispatchEvent(ev);
        if (elUnit) elUnit.dispatchEvent(ev);
        if (elSub) elSub.dispatchEvent(ev);
    } catch(e){}

}

$('.btn-add-item').click(() => {
    let qtd = $("#inp-quantidade").val();
    let value_unit = $("#inp-valor_unitario").val();
    let sub_total = $("#inp-subtotal").val();
    let product_id = $("#inp-produto_id").val();

    if (qtd && value_unit && sub_total && product_id) {
        let dataRequest = {
            qtd: qtd,
            value_unit: value_unit,
            sub_total: sub_total,
            product_id: product_id,
        }

        $.get(path_url + "api/vendas/linhaProdutoVenda", dataRequest)
        .done((e) => {
            if (e.includes("alert(")) {
                // eval(e); // alerta e para
            } else {
                $('.table-itens tbody').append(e);
                calcTotal();

                // só limpa os campos se adicionou produto
                $("#inp-produto_id").val('').change()
                $("#inp-valor_unitario").val('')
                $("#inp-quantidade").val('')
                $("#inp-subtotal").val('')
            }
        })
        .fail((e) => {
            console.log(e);
        })
    } else {
        swal("Atenção", "Informe corretamente os campos para continuar!", "warning")
    }
});


$(function () {
    $('body').on('blur', '.acrescimo, .desconto, .frete', function () {
        setTimeout(() => {
            calcTotal()
            formasPagamento()
            limparCampos()
            calcTotalPayment()
        }, 200)

    })
})

// ===== Taxas da maquineta (cálculo e aplicação) =====
const TM_RATES = {
    master_visa: {1: 4.5, 2: 5.2, 3: 5.9, 4: 6.9, 5: 7.5, 6: 7.8, 7: 8.8, 8: 9.5, 9: 9.9, 10: 10.4, 11: 12.2, 12: 12.6},
    hiper_elo_amex: {1: 4.5, 2: 5.2, 3: 5.9, 4: 6.9, 5: 7.5, 6: 8.5, 7: 9.0, 8: 10.5, 9: 11.0, 10: 12.0, 11: 12.5, 12: 13.0}
};

function tmGetGroupFromBandeira(b) {
    return b; // já vem como 'master_visa' ou 'hiper_elo_amex'
}

function tmGetPercent(bandeira, parcelas) {
    const group = tmGetGroupFromBandeira(bandeira);
    const rates = TM_RATES[group];
    return rates[parcelas] || 0;
}

function ceilToCents(v) {
    return Math.ceil(v * 100) / 100;
}

function ceilToUnit(v){
    return Math.ceil(v);
}

function formatIntBR(v){
    // formata sem casas decimais, separador de milhar com ponto
    try{
        const n = Math.round(v);
        return (n).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }catch(e){
        return String(Math.round(v));
    }
}

function tmUpdatePreview() {
    const valorBase = convertMoedaToFloat($('#tm_valor_base').val() || '0');
    const bandeira = $('#tm_bandeira').val();
    const parcelas = parseInt($('#tm_parcelas').val() || '1');
    const p = tmGetPercent(bandeira, parcelas);
    $('#tm_percent').text(p.toFixed(2).replace('.', ',') + '%');
    if (valorBase > 0 && p > 0) {
        const bruto = ceilToUnit(valorBase / (1 - (p/100)));
        $('#tm_total_com_taxa').text('R$ ' + formatIntBR(bruto));
        const acrescimoSug = bruto - total_geral; // total_geral pode ter decimais; diferença será arredondada ao aplicar
        $('#tm_acrescimo_sugerido').text('R$ ' + formatIntBR(acrescimoSug > 0 ? acrescimoSug : 0));
    } else {
        $('#tm_total_com_taxa').text('R$ 0');
        $('#tm_acrescimo_sugerido').text('R$ 0');
    }
}

// Preenche o modal ao abrir com total atual
$(document).on('shown.bs.modal', '#modal-taxa-maquineta', function(){
    $('#tm_valor_base').val(convertFloatToMoeda(total_geral));
    $('#tm_bandeira').val('master_visa').trigger('change');
    $('#tm_parcelas').val('1').trigger('change');
    setTimeout(() => tmUpdatePreview(), 100);
});

$(document).on('input change', '#tm_valor_base, #tm_bandeira, #tm_parcelas', function(){
    tmUpdatePreview();
});

// Aplicar na venda: ajusta acréscimo, define tipo/forma, gera parcelas e seta bandeira
$(document).on('click', '#tm_btn_aplicar', function(){
    const valorBase = convertMoedaToFloat($('#tm_valor_base').val() || '0');
    const bandeira = $('#tm_bandeira').val();
    const parcelas = parseInt($('#tm_parcelas').val() || '1');
    const p = tmGetPercent(bandeira, parcelas);

    // verificação de parcelamento máximo (limite 12 para maquineta)
    const parcelamento_maximo = Math.min(parseInt($('#parcelamento_maximo').val() || '12'), 12);
    if (parcelas > parcelamento_maximo) {
        swal("Atenção", "Quantidade de parcelas excede o parcelamento máximo ("+parcelamento_maximo+").", "warning");
        return;
    }

    if (valorBase <= 0 || p <= 0) {
        swal("Atenção", "Informe valor base e selecione parcelas/bandeira válidos.", "warning");
        return;
    }

    const bruto = ceilToUnit(valorBase / (1 - (p/100)));
    // aplica acréscimo (ajusta total_geral para igualar bruto)
    const acrescimoAtual = convertMoedaToFloat($('#inp-acrescimo').val() || '0');
    const novoAcrescimo = (bruto - (total_geral)) + acrescimoAtual;
    // garante inteiro e arredonda para cima
    const novoAcrescimoInt = novoAcrescimo > 0 ? ceilToUnit(novoAcrescimo) : 0;
    $('#inp-acrescimo').val(convertFloatToMoeda(novoAcrescimoInt));
    setTimeout(() => {
        calcTotal(); // recalcula total_geral
        setTimeout(() => {
            // define tipo de pagamento como Cartão de Crédito (03)
            $('#inp-tipo_pagamento').val('03').trigger('change');
            // define forma como personalizado e preenche parcelas automaticamente
            $('#inp-forma_pagamento').val('personalizado').trigger('change');

            // define código SEFAZ da bandeira (agrupado)
            // master_visa -> Visa (01), hiper_elo_amex -> Elo (06)
            let codBandeira = '99';
            if (bandeira === 'master_visa') codBandeira = '01';
            else if (bandeira === 'hiper_elo_amex') codBandeira = '06';
            $('#inp-bandeira_cartao').val(codBandeira);

            // gerar parcelas iguais via endpoint já existente
            const data = {
                total_geral: bruto, // inteiro sem casas decimais
                parcelas: parcelas,
                intervalo: 30,
                tipo_pagamento: '03'
            };
            $.get(path_url + 'api/vendas/linhaParcelaVendaPersonalizado', data)
                .done((success) => {
                    $('.table-payment tbody').html(success);
                    calcTotalPayment();
                    $('#modal-taxa-maquineta').modal('hide');
                    swal("Sucesso", "Taxas aplicadas e parcelas geradas!", "success");
                })
                .fail((err) => {
                    console.log(err);
                    swal("Erro", "Falha ao gerar as parcelas.", "error");
                });
        }, 150);
    }, 100);
});

$(function () {
    $('body').on('change', '#inp-forma_pagamento', function () {
        setTimeout(() => {
            formasPagamento()
        }, 200)
    })
})

var total_venda = 0
var total_geral = 0
function calcTotal() {
    var total = 0
    let acrescimo = convertMoedaToFloat($('#inp-acrescimo').val())
    let desconto = convertMoedaToFloat($('#inp-desconto').val())
    let frete = convertMoedaToFloat($('#inp-frete').val())

    $(".subtotal-item").each(function () {
        total += convertMoedaToFloat($(this).val())
    })

    setTimeout(() => {
        total_venda = total
        total_geral = total + acrescimo - desconto + frete
        validateButtonSave()
        $('.total_produtos').html("R$ " + convertFloatToMoeda(total))
        $('.total-venda').html("R$ " + convertFloatToMoeda(total_geral))
    }, 100)
}

$(".table-itens").on('click', '.btn-delete-row', function () {
    $(this).closest('tr').remove();
    swal("Sucesso", "Produto removido!", "success")
    calcTotal()
});

function formasPagamento() {
    // let total_liquido = $('.total-venda').val()
    // console.log(total_liquido)
    let fp = $('#inp-forma_pagamento').val()
    $("#inp-qtd_parcelas").attr("disabled", true);
    $("#inp-data_vencimento").attr("disabled", true);
    $("#inp-valor_integral").attr("disabled", true);

    if (fp == 'a_vista') {
        let now = new Date();
        let data = now.getFullYear() + "-" + ((now.getMonth() + 1) < 10 ? "0" + (now.getMonth() + 1) : (now.getMonth() + 1))
        + "-" + (now.getDate() < 10 ? "0" + now.getDate() : now.getDate())
        $('#inp-qtd_parcelas').val('1')
        $('#inp-data_vencimento').val(data)
        $('#inp-valor_integral').val(convertFloatToMoeda(total_geral))
    } else if (fp == '30_dias') {
        var date = new Date(new Date().setDate(new Date().getDate() + 30));
        let data = date.getFullYear() + "-" + ((date.getMonth() + 1) < 10 ? "0" + (date.getMonth() + 1) : (date.getMonth() + 1))
        + "-" + (date.getDate() < 10 ? "0" + date.getDate() : date.getDate())
        $('#inp-qtd_parcelas').val('1')
        $('#inp-data_vencimento').val(data)
        $('#inp-valor_integral').val(convertFloatToMoeda(total_geral))
    } else if (fp == 'personalizado') {
        $("#inp-qtd_parcelas").removeAttr("disabled");
        $("#inp-data_vencimento").removeAttr("disabled");
        $("#inp-valor_integral").removeAttr("disabled");
        $('#inp-qtd_parcelas').val('1')
        $('#inp-data_vencimento').val('')
        $('#inp-valor_integral').val(convertFloatToMoeda(total_geral))
    } else {

    }
}

// $('#inp-qtd_parcelas').blur(() => {
//     clearPayment()
//     let qtd = $('#inp-qtd_parcelas')
//     $('#inp-valor_integral').val(convertFloatToMoeda(TOTAL / qtd));
// })

function clearPayment() {
    $('#table-payment tbody').html('')
    $("#inp-data_vencimento").val("");
    $("#inp-valor_integral").val("");
    $('.valor_integral').html('')
    $('.btn-add-payment').removeClass("disabled");
}

$('.btn-add-payment').click(() => {
    let tipo_pagamento = $('#inp-tipo_pagamento').val();
    let vencimento = $('#inp-data_vencimento').val();
    let valor_integral = $('#inp-valor_integral').val();

    let v = convertMoedaToFloat(valor_integral)
    total_geral = parseFloat(total_geral.toFixed(2))
    if ((v + total_payment) <= total_geral) {
        if (vencimento && valor_integral && tipo_pagamento) {
            let dataRequest = {
                tipo_pagamento: tipo_pagamento,
                data_vencimento: vencimento,
                valor_integral: valor_integral,
            }

            $.get(path_url + "api/vendas/linhaParcelaVenda", dataRequest)
            .done((e) => {
                $('.table-payment tbody').append(e)
                calcTotalPayment()
            })
            .fail((e) => {
                console.log(e)
            })
        } else {
            swal("Atenção", "Informe corretamente os campos para continuar!", "warning")
        }
    } else {
        swal("Atenção", "A soma das parcelas ultrapassa o valor total da venda", "warning")
    }
})

var total_payment = 0
function calcTotalPayment() {
    var total = 0
    $(".valor_integral").each(function () {
        total += convertMoedaToFloat($(this).val())
    })
    setTimeout(() => {
        total_payment = total
        $('.sum-payment').html("R$ " + convertFloatToMoeda(total))
        validateButtonSave()
    }, 100)
}

$(".table-payment").on('click', '.btn-delete-row', function () {
    $(this).closest('tr').remove();
    swal("Sucesso", "Parcela removida!", "success")
    calcTotal()
});

$('#inp-natureza_id').change(() => {
    validateButtonSave()
})

function validateButtonSave() {
    $('.alerts').html('')
    let cliente_id = $('#inp-cliente_id').val()
    let natureza_id = $('#inp-natureza_id').val()
    let count_pay = $(".table-payment tbody tr").length
    let count_itens = $(".table-itens tbody tr").length

    if (!cliente_id) {
        alertCreate("Selecione o cliente!")
    }
    if (!natureza_id) {
        alertCreate("Selecione a natureza de operação!")
    }
    if (count_itens == 0) {
        alertCreate("Adicione um produto na venda!")
    }
    if (count_pay == 0) {
        alertCreate("Informe o pagamento!")
    }

    setTimeout(() => {
        if ($('.alerts').html() == "") {
            $('.btn-venda').removeAttr("disabled")

        } else {
            $('.btn-venda').attr("disabled", true);

        }
    }, 100)
}

function alertCreate(msg) {
    var div = '<div class="alert lux-resumo-hint mb-2 py-2 px-3 small border-0 rounded-3 shadow-sm" role="alert">'
    div += msg
    div += '</div>'
    $('.alerts').append(div)
}


// REFERENCIA DE CHAVE NFE


function addChave() {
    let chave = $('#inp-chave').val()
    if (chave.length != 44) {
        swal("Erro", "Informe uma chave com 44 nÃºmeros", "error")
    } else {
        REFERENCIASNFE.push(chave)
        let t = montaTabelaChave(); // para remover
        $('.table-chaves tbody').html(t)
    }
}

function montaTabelaChave() {
    let t = "";
    REFERENCIASNFE.map((v) => {

        t += '<tr class="datatable-row">'
        t += '<td class="datatable-cell">'
        t += v
        t += '</td>'
        t += "<td class='datatable-cell'><span class='codigo' style='width: 100px;'><a class='btn btn-danger' onclick='deleteChave(\"" + v + "\")'>"
        t += "<i class='bx bx-trash'></i></a></span></td>";
        t += "</tr>";
    });
    return t
}

function deleteChave(chave) {
    let n = []

    REFERENCIASNFE.map((c) => {
        if (c != chave) n.push(c)
    })
    REFERENCIASNFE = n
    let t = montaTabelaChave(); // para remover
    $('.table-chaves tbody').html(t)
}

let newOption = " "
$('body').on('change', '#inp-filial_id_create', function () {
    $("#inp-produto_id").html(newOption).trigger("change");
    $('#inp-quantidade').val('0,00')
    $('#inp-valor_unitario').val('0,00')
    $('#inp-subtotal').val('0,00')
})


$('#inp-forma_pagamento').change(() => {
    let fp = $('#inp-forma_pagamento').val()
    let tp = $('#inp-tipo_pagamento').val()
    if (fp == 'personalizado') {
        $('#btn-personalizado').removeClass('disabled')
        $('#inp-qtd_parcelas').on('keyup', () => {
            let parcelas = $('#inp-qtd_parcelas').val()
            $('#inp-valor_integral').val(convertFloatToMoeda(total_geral / parcelas));
            $('#valor_integral_personalizado').val(total_geral)
            $('#tipo_pagamento_personalizado').val(tp)
            $('#qtd-parcela_personalizado').val(parcelas)
        })
    }
    else {
        $('#btn-personalizado').addClass('disabled')
    }
})

function renderizarPagamento() {
    simulaParcelas((res) => {
        let html = '';
        res.map((rs) => {
            html += '<option value="' + rs.indice + '">';
            html += rs.indice + 'x R$' + ' ' + convertFloatToMoeda(rs.valor);
            html += '</option>';
        })
        $('#qtd_parcelas').html(html)
    });
}

function simulaParcelas(call) {
    let parcelamento_maximo = parseInt($('#parcelamento_maximo').val())
    let desconto = $('.desconto').val();
    if (desconto.length == 0) desconto = 0;
    else desconto = desconto.replace(",", ".");

    let acrescimo = $('.acrescimo').val();
    if (acrescimo.length == 0) acrescimo = 0;
    else acrescimo = acrescimo.replace(",", ".");

    let temp = [];
    let totalTemp = total_geral.toFixed(2)
    totalTemp = totalTemp.replace(",", ".")

    for (let i = 1; i <= parcelamento_maximo; i++) {
        let vp = totalTemp / i;
        js = {
            'indice': i,
            'valor': vp.toFixed(2)
        }
        temp.push(js)
    }
    call(temp)
}

$('.btn-pag_personalizado').click(() => {
    let parcelas = $('#qtd_parcelas').val()

    let intervalo = $('#inp-intervalo').val()
    let tipo_pagamento = $('#inp-tipo_pagamento').val()
    // total_geral = convertFloatToMoeda(total_geral)
    let data = {
        total_geral: total_geral,
        parcelas: parcelas,
        intervalo: intervalo,
        tipo_pagamento: tipo_pagamento
    }
    $.get(path_url + 'api/vendas/linhaParcelaVendaPersonalizado', data)
    .done((success) => {
        $('.table-payment tbody').html(success)
        calcTotalPayment()
    }).fail((err) => {
        console.log(err)
    })
})

function limparCampos() {
    $('#inp-qtd_parcelas').val('')
    $('#inp-valor_integral').val('')
    $('#inp-data_vencimento').val('')
    $('#inp-forma_pagamento').first().val('').change()
    $('.table-payment tbody').html('')

    // $('.table-payment tbody').each(function () {
    //     $(this).closest('tr').remove();
    // })

}
// function limpaFatura() {

//     $('.table-payment tbody tr').each(function (e, x) {
//         if (e == 0) {
//             setTimeout(() => {
//                 // let total = ($('.total_prod').val())
//                 // $('.valor_fatura').first().val(convertFloatToMoeda(total))
//                 // $('.tipo_pagamento').first().val('').change()
//                 let data = new Date
//                 let dataFormatada = (data.getFullYear() + "-" + adicionaZero((data.getMonth() + 1)) + "-" + adicionaZero(data.getDate()));
//                 $('.date_atual').first().val(dataFormatada)
//                 calcTotalFatura()
//             }, 500)

//         } else {
//             console.log('sim')
//             x.remove();
//         }
//     })
// }
// $(".table-payment").on('click', '.btn-delete-row', function () {
//     $(this).closest('tr').remove();
//     swal("Sucesso", "Parcela removida!", "success")
//     calcTotal()
// });

// function removerVenda(id) {
//     let senha = $('#pass').val()
//     if (senha != "") {
//         swal({
//             title: 'Cancelamento de venda',
//             text: 'Informe a senha!',
//             content: {
//                 element: "input",
//                 attributes: {
//                     placeholder: "Digite a senha",
//                     type: "password",
//                 },
//             },
//             button: {
//                 text: "Cancelar!",
//                 closeModal: false,
//                 type: 'error'
//             },
//             confirmButtonColor: "#DD6B55",
//         }).then(v => {
//             if (v.length > 0) {
//                 $.get(path_url + 'configNF/verificaSenha', { senha: v })
//                     .then(
//                         res => {
//                             // location.href = "/vendas/destroy/" + id;
//                             $.post(path_url + "vendas/destroy/" + id);
//                             // url: path_url + "vendas/destroy/" + id;
//                         },
//                         err => {
//                             swal("Erro", "Senha incorreta", "error")
//                                 .then(() => {
//                                     location.reload()
//                                 });
//                         }
//                     )
//             } else {
//                 location.reload()
//             }
//         })
//     } else {
//         location.href = "/vendas/destroy/" + id;
//     }
// }

