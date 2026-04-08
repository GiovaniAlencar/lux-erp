@extends('default.layout', ['title' => 'Nova Venda'])
@section('content')
<div class="page-content vendas-create-modern">
    {!! Form::open()
    ->post()
    ->route('vendas.store')
    ->id('form-venda')
    ->multipart() 
    !!}
    <div class="row g-3">
        <div class="col-12 col-xxl-8">
            <div class="card">
                <div class="pl-xxl-4">
                    @include('vendas._forms')
                </div>
            </div>
        </div>
        <div class="col-12 col-xxl-4">
            <div class="card">
                <div class="card-body p-4">
                    <div class="card-title d-flex align-items-center">
                        <h6 class="mb-0 text-uppercase">Resumo</h6>
                    </div>
                    <div class="alerts mt-2"></div>
                    <div class="mt-3">
                        <div class="mb-2 small text-muted">Quantidade de Itens</div>
                        <div class="h6 mb-3 qtd-itens-resumo">0 itens</div>
                        <div class="mb-2 small text-muted">Subtotal</div>
                        <div class="h5 mb-3 subtotal-resumo">R$ 0,00</div>
                        <div class="mb-2 small text-muted">Desconto</div>
                        <input type="text" class="form-control form-control-sm resumo-desconto moeda" placeholder="0,00">
                        <div class="mb-2 small text-muted mt-3">Total</div>
                        <div class="h4 fw-bold total-resumo">R$ 0,00</div>
                        <button type="button" class="btn btn-success w-100 mt-3 btn-venda" disabled onclick="salvar('venda')">
                            <i class="bi bi-check-circle"></i> Finalizar Pedido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {!! Form::close() !!}
</div>

 

@section('js')

<script>
    function salvar(t) {
        if (window.__sendingVenda) return;
        window.__sendingVenda = true;
        $(".btn-venda").attr("disabled", true);
        $("#type").val(t)
        $('#form-venda').submit()
    }

    function selectDiv2(ref) {
        $('.btn-outline-primary').removeClass('active')
        if (ref == 'transporte') {
            $('.div-transporte').removeClass('d-none')
            $('.div-itens').addClass('d-none')
            $('.div-pagamento').addClass('d-none')
            $('.btn-transporte').addClass('active')
        }
    }

    function selectDiv2(ref) {
        $('.btn-outline-primary').removeClass('active')
        if (ref == 'transporte') {
            $('.div-transporte').removeClass('d-none')
            $('.div-itens').addClass('d-none')
            $('.div-pagamento').addClass('d-none')
            $('.btn-transporte').addClass('active')
        } else if (ref == 'itens') {
            $('.div-transporte').addClass('d-none')
            $('.div-itens').removeClass('d-none')
            $('.div-pagamento').addClass('d-none')
            $('.btn-itens').addClass('active')
        } else {
            $('.div-transporte').addClass('d-none')
            $('.div-itens').addClass('d-none')
            $('.div-pagamento').removeClass('d-none')
            $('.btn-pagamento').addClass('active')
        }
    }

</script>

<script>
// espelha valores do formulário para o resumo lateral e vice-versa (sem observer global)
document.addEventListener('DOMContentLoaded', function(){
    // garante que a validação inicial considere a natureza padrão (agora hidden)
    const naturezaHidden = document.getElementById('inp-natureza_id');
    if (naturezaHidden) {
        try {
            // dispara o change esperado pelo script de validação
            naturezaHidden.dispatchEvent(new Event('change', { bubbles: true }));
        } catch(e) {}
    }
    if (typeof validateButtonSave === 'function') {
        validateButtonSave();
    }
    const spanTotal = document.querySelector('.total-resumo');
    const spanSubtotal = document.querySelector('.subtotal-resumo');
    const spanQtd = document.querySelector('.qtd-itens-resumo');
    const resumoDesc = document.querySelector('.resumo-desconto');

    let last = { total: null, subtotal: null, qtd: null, desc: null };

    function syncFromForm(){
        const totalVenda = document.querySelector('.total-venda');
        const totalTxt = totalVenda ? totalVenda.textContent.trim() : null;
        if(totalTxt && last.total !== totalTxt){ spanTotal.textContent = totalTxt; last.total = totalTxt; }

        // tenta calcular subtotal a partir dos itens (se houver cálculo anterior no DOM, mantenha)
        const tbody = document.querySelector('.table-itens tbody');
        if(tbody){
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('empty-state'));
            let sub = 0;
            let sumQtd = 0;
            rows.forEach(r => {
                const input = r.querySelector('.subtotal-item');
                if(input && input.value){
                    // remove tudo que não número
                    const val = input.value.replace(/[^\d,]/g,'').replace('.', '').replace(',', '.');
                    const num = parseFloat(val);
                    if(!isNaN(num)) sub += num;
                }
                const qtdInput = r.querySelector('.qtd_row, .qtd-item');
                if (qtdInput && qtdInput.value){
                    const q = qtdInput.value.replace(/[^\d,]/g,'').replace('.', '').replace(',', '.');
                    const qn = parseFloat(q);
                    if(!isNaN(qn)) sumQtd += qn;
                }
            });
            const subTxt = 'R$ ' + (sub.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            if(last.subtotal !== subTxt){ spanSubtotal.textContent = subTxt; last.subtotal = subTxt; }
            const qtdTxt = (sumQtd.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })) + ' un';
            if(last.qtd !== qtdTxt){ spanQtd.textContent = qtdTxt; last.qtd = qtdTxt; }
        }

        if(tbody){
            const rows = tbody.querySelectorAll('tr');
            const count = Array.from(rows).filter(r => r.querySelector('td') && !r.classList.contains('empty-state')).length;
            const empty = tbody.querySelector('.empty-state');
            if(empty){ empty.style.display = count > 0 ? 'none' : ''; }
        }

        const formDesc = document.querySelector('input.desconto');
        if(formDesc && resumoDesc && last.desc !== formDesc.value){ resumoDesc.value = formDesc.value; last.desc = formDesc.value; }
    }

    // do resumo para o form real
    if(resumoDesc){
        resumoDesc.addEventListener('input', function(){
            const formDesc = document.querySelector('input.desconto');
            if(formDesc && formDesc.value !== resumoDesc.value){ formDesc.value = resumoDesc.value; formDesc.dispatchEvent(new Event('input')); }
        });
    }

    // Observadores específicos em vez de body inteiro
    const totalNode = document.querySelector('.total-venda');
    if(totalNode){ new MutationObserver(syncFromForm).observe(totalNode, { childList: true, characterData: true, subtree: true }); }
    const tbody = document.querySelector('.table-itens tbody');
    if(tbody){ new MutationObserver(syncFromForm).observe(tbody, { childList: true, subtree: true }); }

    // Eventos de entrada que afetam totais
    document.addEventListener('input', function(e){
        if(e.target.matches('.qtd, .value_unit, .desconto, .acrescimo, .frete, .qtd_row, .value_unit_row, .subtotal-item')){ 
            syncFromForm(); 
        }
    });

    syncFromForm();
});
</script>

<script type="text/javascript" src="/js/client.js"></script>
<script type="text/javascript" src="/js/vendas.js"></script>
<script type="text/javascript" src="/js/product.js"></script>
<script type="text/javascript" src="/js/transportadora.js"></script>

@endsection

@include('modals._ref-nfe', ['not_submit' => true])
@include('modals._produto', ['not_submit' => true])
@include('modals._client_quick', ['not_submit' => true])
@include('modals._cliente_endereco')
@include('modals._cliente_perfil')
@include('modals._transportadora', ['not_submit' => true])
@include('modals._pagamento_personalizado', ['not_submit' => true])
@include('modals._taxa_maquineta')
@include('vendas.partials.modal_edit_item')

@endsection
