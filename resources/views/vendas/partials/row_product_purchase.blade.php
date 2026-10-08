@php
    use App\Helpers\PrecoCategoriaVenda;
    $grupoPreco = PrecoCategoriaVenda::grupoPorCategoriaId((int) $product->categoria_id);
    $precoNormal = (float) ($product->valor_venda ?? 0);
    $precoAtacado1 = $product->preco_2 !== null ? (float) $product->preco_2 : $precoNormal;
    $precoAtacado2 = $product->preco_3 !== null ? (float) $product->preco_3 : ($product->preco_2 !== null ? (float) $product->preco_2 : $precoNormal);
    $tierInicial = $tabela_aplicada ?? PrecoCategoriaVenda::TIER_NORMAL;
    $tierLabel = PrecoCategoriaVenda::labelTier($tierInicial);
    $badgeClass = PrecoCategoriaVenda::badgeClassTier($tierInicial);
@endphp
<tr class="tr_{{ $rand }} linha-item-venda"
    data-produto-id="{{ $product->id }}"
    data-categoria-id="{{ $product->categoria_id }}"
    data-grupo-preco="{{ $grupoPreco ?? '' }}">
    <td class="d-none d-xl-table-cell">
        <input readonly type="tel" name="produto_id[]" class="form-control form-control-sm" value="{{ $product->id }}">
    </td>
    <td>
        <input readonly type="text" name="produto_nome[]" class="form-control form-control-sm" value="{{ $product->nome }}">
        <input type="hidden" class="inp-preco-normal" value="{{ $precoNormal }}">
        <input type="hidden" class="inp-preco-atacado-1" value="{{ $precoAtacado1 }}">
        <input type="hidden" class="inp-preco-atacado-2" value="{{ $precoAtacado2 }}">
        <div class="mt-1 small linha-tabela-preco-info">
            @if($grupoPreco)
            <span class="badge rounded-pill {{ $badgeClass }} badge-tabela-aplicada">{{ $tierLabel }}</span>
            <span class="text-muted ms-1">Tabela: {{ $tierLabel }}</span>
            @endif
        </div>
    </td>
    <td>
        <input readonly type="tel" name="valor_unitario[]" class="form-control form-control-sm value_unit_row" value="{{ __moeda($value_unit) }}">
    </td>
    <td>
        <input readonly type="tel" name="quantidade[]" class="form-control form-control-sm qtd-item qtd_row" value="{{ $qtd }}">
    </td>
    <td>
        <input type="hidden" name="x_pedido[]" class="x_pedido_row" value="">
        <input type="hidden" name="num_item_pedido[]" class="num_item_pedido_row" value="">
        <input readonly type="tel" name="subtotal_item[]" class="form-control form-control-sm subtotal-item" value="{{ __moeda($sub_total) }}">
        <input type="hidden" name="cfop[]" value="{{ $product->CFOP_saida_estadual }}">
    </td>
    <td class="text-end">
        <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-danger btn-delete-row">
                <i class="bi bi-trash"></i>
            </button>
            <button type="button" class="btn btn-sm btn-warning btn-edit" onclick="editItem('{{ $rand }}')">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>
    </td>
</tr>
