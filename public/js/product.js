$(function () {
    $('[data-bs-toggle="popover"]').popover();
});

function selectDiv(ref) {
    $('button').removeClass('link-active')
    if (ref == 'aliquotas') {
        $('.div-aliquotas').removeClass('d-none')
        $('.div-identificacao').addClass('d-none')
        $('.btn-aliquotas').addClass('link-active')

    } else {
        $('.div-aliquotas').addClass('d-none')
        $('.div-identificacao').removeClass('d-none')
        $('.btn-identificacao').addClass('link-active')

    }
}

$('#inp-derivado_petroleo').change(() => {
    isPetroleo()
})

function isPetroleo() {
    let is = $('#inp-derivado_petroleo').val()
    if (is == 1) {
        $('.d-pet').removeClass('d-none')
    } else {
        $('.d-pet').addClass('d-none')

    }
}

$('#inp-composto').change(() => {
    isComposto()
})

function isComposto() {
    let is = $('#inp-composto').val()
    if (is == 1) {
        $('.d-comp').removeClass('d-none')
    } else {
        $('.d-comp').addClass('d-none')

    }
}

$('#inp-ecommerce').change(() => {
    isEcommerce()
})

function isEcommerce() {
    let is = $('#inp-ecommerce').val()
    if (is == 1) {
        $('.d-ecommerce').removeClass('d-none')
    } else {
        $('.d-ecommerce').addClass('d-none')
    }
}

$('#inp-locacao').change(() => {
    isLocacao()
})

function isLocacao() {
    let is = $('#inp-locacao').val()
    if (is == 1) {
        $('.d-locacao').removeClass('d-none')
    } else {
        $('.d-locacao').addClass('d-none')
    }
}


$('#inp-lote-vencimento').change(() => {
    isLoteVencimento()
})

function isLoteVencimento() {
    let is = $('#inp-lote-vencimento').val()
    if (is == 1) {
        $('.d-lote').removeClass('d-none')
    } else {
        $('.d-lote').addClass('d-none')
    }
}

$('#inp-dados-veiculo').change(() => {
    isDadosVeiculo()
})

function isDadosVeiculo() {
    let is = $('#inp-dados-veiculo').val()
    if (is == 1) {
        $('.d-dados').removeClass('d-none')
    } else {
        $('.d-dados').addClass('d-none')
    }
}

// $('#btn-store').click(() => {
//     let valid = validaCamposModal()
//     if (valid.length > 0) {
//         let msg = ""
//         valid.map((x) => {
//             msg += x + "\n"
//         })
//         swal("Ops, erro no formulário", msg, "error")
//     } else {
//         console.log("salvando...")

//         let data = {}
//         $(".modal input, .modal select").each(function () {

//             let indice = $(this).attr('id')
//             indice = indice.substring(4, indice.length)
//             data[indice] = $(this).val()
//         });
//         data['empresa_id'] = $('#empresa_id').val()

//         console.log(data)
//         $.post(path_url + 'api/categoria/store', data)
//             .done((success) => {
//                 console.log("success", success)
//                 swal("Sucesso", "Categoria cadastrado!", "success")
//                     .then(() => {
//                         var newOption = new Option(success.nome, success.id, false, false);
//                         $('#inp-categoria_id').append(newOption).trigger('change');
//                         $('#modal-categoria').modal('hide')
//                     })

//             }).fail((err) => {
//                 console.log(err)
//                 swal("Ops", "Algo deu errado ao salvar categoria!", "error")
//             })
//     }
// })

$('#btn-codBarras').click(() => {
    $.get(path_url + 'api/produtos/getBarcode')
        .done((success) => {
            $('#inp-codBarras').val(success)
        }).fail((err) => {
            console.log(err)
        })
})

$('#btn-store-categoria').click(() => {
    let nome = $('#inp-nome_categoria').val()
    if (nome) {
        let js = {
            empresa_id: $('#empresa_id').val(),
            nome: nome,
            _token: '{{ csrf_token() }}'
        }
        $.post(path_url + 'api/categorias/storeCategoria', js)
            .done((data) => {
                $('#inp-categoria_id')
                var newOption = new Option(data.nome, data.id, false, false);
                $('#inp-categoria_id').append(newOption).trigger('change');
                $('#modal-categoria').modal('hide')
            }).fail((err) => {
                console.log(err)
            })
    } else {
        swal("Erro", "Informe o nome da categoria", "warning")
    }
})


$(function () {
    initPrecificacaoProduto();
});

function initPrecificacaoProduto() {
    const pares = {
        normal: { perc: '#inp-percentual_lucro', preco: '#inp-valor_venda' },
        atacado_1: { perc: '#inp-percentual_lucro_atacado_1', preco: '#inp-preco_2' },
        atacado_2: { perc: '#inp-percentual_lucro_atacado_2', preco: '#inp-preco_3' },
    };

    $('#inp-valor_compra').on('input keyup change', function () {
        syncPrecificacaoProduto('custo');
    });

    $('#inp-percentual_lucro').on('input keyup change', function () {
        syncPrecificacaoProduto('perc', 'normal');
    });
    $('#inp-valor_venda').on('input keyup change', function () {
        syncPrecificacaoProduto('preco', 'normal');
    });

    $('#inp-percentual_lucro_atacado_1').on('input keyup change', function () {
        syncPrecificacaoProduto('perc', 'atacado_1');
    });
    $('#inp-preco_2').on('input keyup change', function () {
        syncPrecificacaoProduto('preco', 'atacado_1');
    });

    $('#inp-percentual_lucro_atacado_2').on('input keyup change', function () {
        syncPrecificacaoProduto('perc', 'atacado_2');
    });
    $('#inp-preco_3').on('input keyup change', function () {
        syncPrecificacaoProduto('preco', 'atacado_2');
    });

    // Normaliza % ao carregar (evita decimais longos vindos do banco)
    Object.keys(pares).forEach(function (par) {
        const custo = parseMoedaInput($('#inp-valor_compra'));
        const preco = parseMoedaInput($(pares[par].preco));
        if (custo > 0 && preco > 0) {
            setPercField($(pares[par].perc), calcLucroFromPreco(custo, preco));
        }
    });
}

function parseMoedaInput($el) {
    const raw = ($el.val() || '').toString().trim();
    if (!raw) return 0;
    if (raw.includes(',')) {
        return parseFloat(raw.replace(/\./g, '').replace(',', '.')) || 0;
    }
    return parseFloat(raw) || 0;
}

function roundMoney(n) {
    return Math.round(n * 100) / 100;
}

function calcPrecoFromLucro(custo, perc) {
    if (custo <= 0 || perc <= 0) return 0;
    return roundMoney(custo + (custo * perc / 100));
}

function calcLucroFromPreco(custo, preco) {
    if (custo <= 0 || preco <= 0) return 0;
    return roundMoney(((preco - custo) / custo) * 100);
}

function setMoedaField($el, valor) {
    if (valor <= 0) {
        $el.val('');
        return;
    }
    if (typeof convertFloatToMoeda === 'function') {
        $el.val(convertFloatToMoeda(valor));
    } else {
        $el.val(valor.toFixed(2).replace('.', ','));
    }
}

function setPercField($el, valor) {
    if (valor <= 0) {
        $el.val('');
        return;
    }
    $el.val(roundMoney(valor).toFixed(2).replace('.', ','));
}

function reajusteAutomaticoAtivo() {
    const $campo = $('#inp-reajuste_automatico');
    return $campo.length === 0 || $campo.val() == '1';
}

function syncPrecificacaoProduto(tipo, par) {
    const custo = parseMoedaInput($('#inp-valor_compra'));
    const mapa = {
        normal: { perc: '#inp-percentual_lucro', preco: '#inp-valor_venda' },
        atacado_1: { perc: '#inp-percentual_lucro_atacado_1', preco: '#inp-preco_2' },
        atacado_2: { perc: '#inp-percentual_lucro_atacado_2', preco: '#inp-preco_3' },
    };

    if (tipo === 'custo' && reajusteAutomaticoAtivo()) {
        ['normal', 'atacado_1', 'atacado_2'].forEach(function (p) {
            const perc = parseMoedaInput($(mapa[p].perc));
            if (custo > 0 && perc > 0) {
                setMoedaField($(mapa[p].preco), calcPrecoFromLucro(custo, perc));
            }
        });
        return;
    }

    if (!par || !mapa[par]) return;

    const $perc = $(mapa[par].perc);
    const $preco = $(mapa[par].preco);

    if (tipo === 'perc') {
        const perc = parseMoedaInput($perc);
        if (custo > 0 && perc > 0) {
            setMoedaField($preco, calcPrecoFromLucro(custo, perc));
        }
    }

    if (tipo === 'preco') {
        const preco = parseMoedaInput($preco);
        if (custo > 0 && preco > 0) {
            setPercField($perc, calcLucroFromPreco(custo, preco));
        }
    }
}

function formatReal(v) {
    return v.toLocaleString('pt-br', { style: 'currency', currency: 'BRL', minimumFractionDigits: casas_decimais });
}


$('#btn-store-sub_categoria').click(() => {
    let nome = $('#inp-nome_sub_categoria').val()
    let categoria_id = $('#inp-categoria_id').val()

    if (!categoria_id) {
        swal("Erro", "Informe a categoria", "warning")
    } else if (!nome) {
        swal("Erro", "Informe nome", "warning")
    } else {
        $.post(path_url + 'api/categorias/storesubCategoria',
            {
                _token: $('#token').val(),
                nome: nome,
                categoria_id: categoria_id
            })
            .done((res) => {

                $('#inp-sub_categoria_id').append('<option value="' + res.id + '">' +
                    res.nome + '</option>').change();
                $('#inp-sub_categoria_id').val(res.id).change();
                swal("Sucesso", "Sub Categoria adicionada!!", 'success')
                    .then(() => {
                        $('#modal-sub_categoria').modal('hide')
                    })
            })
            .fail((err) => {
                console.log(err)
                swal("Erro", "Algo deu errado!!", 'error')

            })
    }
})

$('#btn-store-marca').click(() => {
    let nome = $('#inp-nome_marca').val()
    if (nome) {
        let js = {
            empresa_id: $('#empresa_id').val(),
            nome: nome,
            _token: '{{ csrf_token() }}'
        }
        $.post(path_url + 'api/marcas/store', js)
            .done((data) => {
                $('#inp-marca_id')
                var newOption = new Option(data.nome, data.id, false, false);
                $('#inp-marca_id').append(newOption).trigger('change');
                $('#modal-marca').modal('hide')
                swal("Sucesso", "Marca adicionada!!", 'success')
                    .then(() => {
                        $('#modal-marca').modal('hide')
                    })
            }).fail((err) => {
                console.log(err)
            })
    } else {
        swal("Erro", "Informe o nome da marca", "warning")
    }
})
