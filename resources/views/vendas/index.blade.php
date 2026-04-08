@extends('default.layout',['title' => 'Vendas'])
@section('content')
<div class="page-content">
    <div class="card ">
        <div class="card-body p-4">
            <div class="page-breadcrumb d-sm-flex align-items-center mb-3">
                <div class="ms-auto">
                </div>
            </div>
            <div class="col">
                @if(isset($config))
                <input type="hidden" id="pass" value="{{ $config->senha_remover }}">
                @endif
                <h6 class="mb-0 text-uppercase">Buscar</h6>
                {!!Form::open()->fill(request()->all())
                ->get()
                !!}
                <div class="filter-bar">
                    <div class="row align-items-end g-3">
                    <!-- <div class="col-md-2">
                        {!!Form::select('tipo', 'Tipo de pesquisa',
                        [0 => 'Razão Social',
                        1 => 'Nome Fantasia',
                        ])
                        ->attrs(['class' => 'select2'])
                        !!}
                    </div> -->
                    <div class="col-md-4">
                        {!!Form::select('cliente_id', 'Cliente')
                        !!}
                    </div>
                    <div class="col-md-2">
                        {!!Form::select('pesquisa_data', 'Pesquisa por data',
                        ['created_at' => 'Data Registro',
                        'data_entrega' => 'Data Entrega'])
                        ->attrs(['class' => 'select2'])
                        !!}
                    </div>
                    <div class="col-md-2">
                        {!!Form::date('start_date', 'Data inicial')
                        !!}
                    </div>
                    <div class="col-md-2">
                        {!!Form::date('end_date', 'Data final')
                        !!}
                    </div>
                    @if(empresaComFilial())
                    {!! __view_locais_select_filtro("Local", isset($filial_id) ? $filial_id : '') !!}
                    @endif
                    <div class="col-auto filter-actions">
                        <button class="btn btn-primary" type="submit"> <i class="bi bi-search"></i></button>
                        <a id="clear-filter" class="btn btn-danger" href="{{ route('vendas.index') }}"><i class="bi bi-eraser"></i></a>
                    </div>
                    </div>
                </div>
                {!!Form::close()!!}
            </div>
            @isset($data->appends)
            {!! $data->appends(request()->all())->links() !!}
            @endisset
        </div>
    </div>
    <div class="card ">
        <div class="card-body p-4">
            <div class="page-breadcrumb d-sm-flex align-items-center mb-3">
                <div class="ms-auto">
                </div>
            </div>
            <div class="col">
                <h6 class="mb-0 text-uppercase">Lista de vendas</h6>
                {{-- <p>Registros: {{ $data->total() }}</p> --}}
                <br />
                <div class="row">
                    <div class="col-12">
                        <a href="{{ route('vendas.create')}}" type="button" class="btn btn-success">
                            <i class="bi bi-plus"></i> Nova venda
                        </a>
                        <div class="d-inline-flex gap-2 ms-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-select-all">
                                Selecionar todos
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" id="btn-print-selected">
                                Imprimir selecionados
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-routes-txt">
                                Gerar rotas (TXT)
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-body p-0">
                        <div class="table-responsive tbl-400" style="height: 500px !important;">
                            <table class="table table-modern actions-right">
                                <thead class="">
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="check-all">
                                        </th>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Data de Registro</th>
                                        <!-- <th>Tipo de Pagamento</th> -->
                                        <!-- @if(empresaComFilial())
                                        <th>Local</th>
                                        @endif -->
                                        <!-- <th>Estado</th> -->
                                        <!-- <th>NFe</th> -->
                                        <!-- <th>Usuário</th> -->
                                        <th>Valor Integral</th>
                                        <th>Desconto</th>
                                        <th>Acréscimo</th>
                                        <th>Frete</th>
                                        <!-- <th>Ecommerce</th> -->
                                        <th>Valor Total</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data as $item)
                                    <tr>
                                        <td>
                                            <input type="checkbox" value="{{$item->id}}" class="checkbox-venda">
                                        </td>
                                        <td>
                                            {{$item->id}}
                                        </td>
                                        <td>{{ $item->cliente->razao_social }}</td>
                                        <td>{{ __data_pt($item->created_at, 1) }}</td>
                                        <td>{{ __moeda($item->valor_total) }}</td>
                                        <td>{{ __moeda($item->desconto) }}</td>
                                        <td>{{ __moeda($item->acrescimo) }}</td>
                                        <td>{{ __moeda($item->frete) }}</td>
                                        <td>{{ __moeda($item->valor_total - $item->desconto + $item->acrescimo + $item->frete) }}</td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <form action="{{ route('vendas.destroy', $item->id) }}" method="post" id="form-{{$item->id}}" style="display: inline-block;">
                                                @method('delete')
                                                @csrf
                                                <div class="btn-group btn-action-group" role="group">
  <a href="{{ route('vendas.show', $item->id) }}" class="btn btn-outline-primary" title="Visualizar">
    <i class="bi bi-eye"></i>
  </a>
  <a href="{{ route('vendas.edit', $item->id) }}" class="btn btn-outline-dark" title="Editar">
    <i class="bi bi-pencil"></i>
  </a>
  <a href="{{ route('vendas.print', $item->id) }}" target="_blank" class="btn btn-outline-warning" title="Imprimir">
    <i class="bi bi-printer"></i>
  </a>
  @if($item->estado_emissao == 'novo' || $item->estado_emissao == 'rejeitado')
  <button type="submit" class="btn btn-outline-danger btn-delete" title="Apagar">
    <i class="bi bi-trash"></i>
  </button>
  @endif
</div>
                                            </form>
                                        </td>


                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="14" class="text-center">Nada encontrado</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @isset($data->appends)
            {!! $data->appends(request()->all())->links() !!}
            @endisset
        </div>
    </div>
</div>
<div class="modal fade" id="modal-cancelar" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar NFe <strong class="text-danger numero_nfe"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12">
                    {!! Form::text('motivo-cancela', 'Justificativa') !!}
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancelar-send" type="button" class="btn btn-danger px-5">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modal-corrigir" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Corrigir NFe <strong class="text-warning numero_nfe"></strong></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="col-md-12">
                    {!! Form::text('motivo-corrige', 'Descrição da correção') !!}
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-corrige-send" type="button" class="btn btn-warning px-5">Corrigir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-inutilizar" aria-modal="true" role="dialog" style="overflow:scroll;" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">INUTILIZAÇÃO DE NÚMERO(s) DE NFe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        {!! Form::tel('numero_inicial', 'Nº inicial') !!}
                    </div>
                    <div class="col-md-3">
                        {!! Form::tel('numero_final', 'Nº final') !!}
                    </div>
                    <div class="col-md-12 mt-3">
                        {!! Form::text('motivo-inutiliza', 'Justificativa') !!}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-inutiliza-send" type="button" class="btn btn-primary px-5">Inutilizar</button>
            </div>
        </div>
    </div>
</div>

@include('modals._email', ['not_submit' => true])


@endsection

@section('js')

<script type="text/javascript" src="/js/nf.js"></script>
<script type="text/javascript" src="/js/vendas.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
  const dateInputs = document.querySelectorAll('input[type="date"]');

  dateInputs.forEach(input => {
    input.addEventListener('click', function(e) {
      // se clicou no ícone, não faz nada
      if (e.offsetX > this.clientWidth - 30) return;

      // força a abertura do datepicker
      this.showPicker?.(); // browsers modernos
      this.focus(); // fallback
    });
  });
});

// Seleção em massa
document.addEventListener('DOMContentLoaded', function(){
    const checkAll = document.getElementById('check-all');
    const selectAllBtn = document.getElementById('btn-select-all');
    const printBtn = document.getElementById('btn-print-selected');
    const txtBtn = document.getElementById('btn-routes-txt');

    function getSelectedIds(){
        return Array.from(document.querySelectorAll('.checkbox-venda:checked')).map(i => i.value);
    }
    function setAllChecked(val){
        document.querySelectorAll('.checkbox-venda').forEach(cb => cb.checked = val);
        if (checkAll) checkAll.checked = val;
    }
    if (checkAll){
        checkAll.addEventListener('change', () => setAllChecked(checkAll.checked));
    }
    if (selectAllBtn){
        selectAllBtn.addEventListener('click', () => setAllChecked(true));
    }
    if (printBtn){
        printBtn.addEventListener('click', () => {
            const ids = getSelectedIds();
            if(ids.length === 0){
                swal("Atenção", "Selecione pelo menos uma venda!", "warning");
                return;
            }
            // abre cada impressão em nova aba com pequeno intervalo
            let delay = 0;
            ids.forEach(id => {
                setTimeout(() => {
                    window.open("{{ route('vendas.print', ':id') }}".replace(':id', id), '_blank');
                }, delay);
                delay += 300;
            });
        });
    }
    if (txtBtn){
        txtBtn.addEventListener('click', () => {
            const ids = getSelectedIds();
            if(ids.length === 0){
                swal("Atenção", "Selecione pelo menos uma venda!", "warning");
                return;
            }
            // envia para backend gerar TXT
            fetch("{{ route('vendas.routes-txt') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ ids })
            }).then(async (res) => {
                if(!res.ok) throw new Error('Erro ao gerar TXT');
                const blob = await res.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'rotas-{{ date("Ymd") }}.txt';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            }).catch(err => {
                console.log(err);
                swal("Erro", "Não foi possível gerar o TXT.", "error");
            });
        });
    }
});

</script>
@endsection