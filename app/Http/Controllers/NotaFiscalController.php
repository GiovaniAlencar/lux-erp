<?php

namespace App\Http\Controllers;

use App\Models\ConfigNota;
use App\Models\NaturezaOperacao;
use App\Models\NotaFiscal;
use App\Models\Venda;
use App\Services\NotaFiscalEmissor;
use Illuminate\Http\Request;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Daevento;

class NotaFiscalController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->queryFiltrada($request);
        $totalAutorizadas = (clone $q)->where('status', 'autorizada')->sum('valor_total');
        $notas = $q->orderByDesc('id')->paginate(50)->appends($request->all());

        return view('notas_fiscais.index', compact('notas', 'totalAutorizadas'));
    }

    private function queryFiltrada(Request $request)
    {
        return NotaFiscal::with(['cliente:id,razao_social,cpf_cnpj', 'usuario:id,nome'])
            ->where('empresa_id', $request->empresa_id)
            ->when($request->status, fn ($w) => $w->where('status', $request->status))
            ->when($request->ambiente, fn ($w) => $w->where('ambiente', (int) $request->ambiente))
            ->when($request->numero, fn ($w) => $w->where('numero', (int) $request->numero))
            ->when($request->venda_id, fn ($w) => $w->where('venda_id', (int) $request->venda_id))
            ->when($request->data_inicial, fn ($w) => $w->whereDate('data_emissao', '>=', $request->data_inicial))
            ->when($request->data_final, fn ($w) => $w->whereDate('data_emissao', '<=', $request->data_final))
            ->when($request->cliente, function ($w) use ($request) {
                $w->whereHas('cliente', fn ($c) => $c->where(function ($x) use ($request) {
                    $x->where('razao_social', 'like', '%' . $request->cliente . '%')
                        ->orWhere('cpf_cnpj', 'like', '%' . $request->cliente . '%');
                }));
            });
    }

    public function criarDaVenda($vendaId)
    {
        $venda = Venda::findOrFail($vendaId);
        if (!__valida_objeto($venda)) {
            abort(403);
        }
        try {
            $nota = (new NotaFiscalEmissor())->rascunhoDaVenda($venda);
        } catch (\Throwable $e) {
            return redirect()->back()->with('flash_erro', $e->getMessage());
        }
        return redirect()->route('notas-fiscais.conferir', $nota->id);
    }

    public function conferir($id)
    {
        $nota = $this->carregar($id);
        $nota->load(['itens.produto', 'cliente.cidade', 'natureza', 'venda']);
        $emissor = new NotaFiscalEmissor();
        $pendencias = $nota->editavel() ? $emissor->validar($nota) : [];
        $naturezas = NaturezaOperacao::where('empresa_id', $nota->empresa_id)->get();
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();

        return view('notas_fiscais.conferir', compact('nota', 'pendencias', 'naturezas', 'config'));
    }

    public function salvar(Request $request, $id)
    {
        $nota = $this->carregar($id);
        if (!$nota->editavel()) {
            return redirect()->route('notas-fiscais.conferir', $nota->id)->with('flash_erro', 'Nota já transmitida: não pode ser alterada.');
        }
        $nota->load('itens');

        $data = $request->input('data_emissao');
        $nota->data_emissao = $data ? \Carbon\Carbon::parse($data) : now();
        if ($request->filled('natureza_id')) {
            $nota->natureza_id = (int) $request->natureza_id;
        }
        if (array_key_exists($request->tipo_pagamento, NotaFiscal::PAGAMENTOS)) {
            $nota->tipo_pagamento = $request->tipo_pagamento;
        }
        $nota->valor_frete = $this->num($request->valor_frete);
        $nota->valor_desconto = $this->num($request->valor_desconto);
        $nota->valor_outros = $this->num($request->valor_outros);
        $nota->info_complementar = mb_substr((string) $request->info_complementar, 0, 2000);

        $remover = array_map('intval', (array) $request->input('remover', []));
        foreach ($nota->itens as $it) {
            if (in_array($it->id, $remover, true)) {
                $it->delete();
                continue;
            }
            $dados = $request->input('itens.' . $it->id);
            if (is_array($dados)) {
                $it->quantidade = max(0, $this->num($dados['quantidade'] ?? $it->quantidade));
                $it->valor_unitario = max(0, $this->num($dados['valor_unitario'] ?? $it->valor_unitario, 4));
                $it->save();
            }
        }
        $nota->unsetRelation('itens');
        $nota->recalcularTotais();
        $nota->save();

        if ($request->input('acao') === 'transmitir') {
            $res = (new NotaFiscalEmissor())->transmitir($nota->fresh());
            return redirect()->route('notas-fiscais.conferir', $nota->id)
                ->with($res['ok'] ? 'flash_sucesso' : 'flash_erro', $res['msg']);
        }

        return redirect()->route('notas-fiscais.conferir', $nota->id)->with('flash_sucesso', 'Rascunho salvo.');
    }

    public function consultar($id)
    {
        $nota = $this->carregar($id);
        try {
            $res = (new NotaFiscalEmissor())->consultar($nota);
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'msg' => $e->getMessage()];
        }
        return redirect()->route('notas-fiscais.conferir', $nota->id)
            ->with($res['ok'] ? 'flash_sucesso' : 'flash_erro', $res['msg']);
    }

    public function cancelar(Request $request, $id)
    {
        $nota = $this->carregar($id);
        try {
            $res = (new NotaFiscalEmissor())->cancelar($nota, (string) $request->input('motivo'));
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'msg' => $e->getMessage()];
        }
        return redirect()->route('notas-fiscais.conferir', $nota->id)
            ->with($res['ok'] ? 'flash_sucesso' : 'flash_erro', $res['msg']);
    }

    public function cce(Request $request, $id)
    {
        $nota = $this->carregar($id);
        try {
            $res = (new NotaFiscalEmissor())->cartaCorrecao($nota, (string) $request->input('correcao'));
        } catch (\Throwable $e) {
            $res = ['ok' => false, 'msg' => $e->getMessage()];
        }
        return redirect()->route('notas-fiscais.conferir', $nota->id)
            ->with($res['ok'] ? 'flash_sucesso' : 'flash_erro', $res['msg']);
    }

    /** PDF do evento (cancelamento ou carta de correção). */
    public function eventoPdf($id, $tipo)
    {
        $nota = $this->carregar($id);
        $path = $nota->xmlEventoPath($tipo === 'cancelamento' ? 'cancelamento' : 'correcao');
        if (!$path) {
            return redirect()->back()->with('flash_erro', 'XML do evento não encontrado.');
        }
        $config = ConfigNota::with('cidade')->where('empresa_id', $nota->empresa_id)->first();
        $emitente = [
            'razao' => $config->razao_social,
            'logradouro' => $config->logradouro,
            'numero' => $config->numero,
            'complemento' => (string) $config->complemento,
            'bairro' => $config->bairro,
            'CEP' => $config->cep,
            'municipio' => optional($config->cidade)->nome ?: $config->municipio,
            'UF' => optional($config->cidade)->uf ?: $config->UF,
            'telefone' => $config->fone,
            'email' => (string) $config->email,
        ];
        $daevento = new Daevento(file_get_contents($path), $emitente);
        $pdf = $daevento->render(null);
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . ($tipo === 'cancelamento' ? 'Cancelamento' : 'CCe') . '-' . $nota->numero . '.pdf"');
    }

    /** Exporta a listagem filtrada em CSV (abre no Excel). */
    public function exportar(Request $request)
    {
        $notas = $this->queryFiltrada($request)->orderBy('numero')->get();
        $linhas = [['Numero', 'Serie', 'Emissao', 'Status', 'Ambiente', 'Cliente', 'CPF/CNPJ', 'Pedido', 'Produtos', 'Frete', 'Desconto', 'Outros', 'Total', 'Chave', 'Protocolo']];
        foreach ($notas as $n) {
            $linhas[] = [
                $n->numero, $n->serie, optional($n->data_emissao)->format('d/m/Y H:i'),
                \App\Models\NotaFiscal::STATUS[$n->status] ?? $n->status,
                (int) $n->ambiente === 1 ? 'Producao' : 'Homologacao',
                optional($n->cliente)->razao_social, optional($n->cliente)->cpf_cnpj, $n->venda_id,
                number_format((float) $n->valor_produtos, 2, ',', ''), number_format((float) $n->valor_frete, 2, ',', ''),
                number_format((float) $n->valor_desconto, 2, ',', ''), number_format((float) $n->valor_outros, 2, ',', ''),
                number_format((float) $n->valor_total, 2, ',', ''), $n->chave ? "'" . $n->chave : '', $n->protocolo,
            ];
        }
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($linhas as $l) {
            fputcsv($out, $l, ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="notas-fiscais-' . date('Ymd-His') . '.csv"');
    }

    /** ZIP com os XMLs (NF-e + eventos) das notas autorizadas/canceladas do filtro — para o contador. */
    public function xmlsZip(Request $request)
    {
        if (!class_exists(\ZipArchive::class)) {
            return redirect()->back()->with('flash_erro', 'Extensão zip do PHP desativada.');
        }
        $notas = $this->queryFiltrada($request)->whereIn('status', ['autorizada', 'cancelada'])->get();
        if ($notas->isEmpty()) {
            return redirect()->back()->with('flash_erro', 'Nenhuma nota autorizada/cancelada no filtro.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'nfzip');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $qtd = 0;
        foreach ($notas as $n) {
            if ($p = $n->xmlPath()) {
                $zip->addFile($p, 'nfe/' . $n->chave . '-nfe.xml');
                $qtd++;
            }
            if ($p = $n->xmlEventoPath('cancelamento')) {
                $zip->addFile($p, 'eventos/' . $n->chave . '-cancelamento.xml');
            }
            if ($p = $n->xmlEventoPath('correcao')) {
                $zip->addFile($p, 'eventos/' . $n->chave . '-cce.xml');
            }
        }
        $zip->close();
        if ($qtd === 0) {
            @unlink($tmp);
            return redirect()->back()->with('flash_erro', 'Nenhum XML encontrado no servidor para o filtro.');
        }
        return response()->download($tmp, 'xmls-nfe-' . date('Ymd-His') . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function excluir($id)
    {
        $nota = $this->carregar($id);
        if (!$nota->editavel()) {
            return redirect()->back()->with('flash_erro', 'Só é possível excluir rascunho ou nota rejeitada.');
        }
        $vendaId = $nota->venda_id;
        $nota->itens()->delete();
        $nota->delete();
        return $vendaId
            ? redirect()->route('vendas.show', $vendaId)->with('flash_sucesso', 'Rascunho da NF-e excluído.')
            : redirect()->route('notas-fiscais.index')->with('flash_sucesso', 'Rascunho da NF-e excluído.');
    }

    public function danfe($id)
    {
        $nota = $this->carregar($id);
        $path = $nota->xmlPath();
        if (!$path) {
            return redirect()->back()->with('flash_erro', 'XML da nota não encontrado.');
        }
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $logo = null;
        if ($config && $config->logo && file_exists(public_path('uploads/configEmitente/' . $config->logo))) {
            $logo = 'data://text/plain;base64,' . base64_encode(file_get_contents(public_path('uploads/configEmitente/' . $config->logo)));
        }
        $danfe = new Danfe(file_get_contents($path));
        $danfe->setVUnComCasasDec((int) ($config->casas_decimais ?? 2));
        $pdf = $danfe->render($logo);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="DANFE-' . $nota->numero . '.pdf"');
    }

    public function xml($id)
    {
        $nota = $this->carregar($id);
        $path = $nota->xmlPath();
        if (!$path) {
            return redirect()->back()->with('flash_erro', 'XML da nota não encontrado.');
        }
        return response()->download($path, $nota->chave . '.xml', ['Content-Type' => 'application/xml']);
    }

    private function carregar($id): NotaFiscal
    {
        $nota = NotaFiscal::findOrFail($id);
        if ((int) $nota->empresa_id !== (int) request()->empresa_id) {
            abort(403);
        }
        return $nota;
    }

    private function num($v, int $dec = 2): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return round((float) $v, $dec);
        }
        return round((float) __convert_value_bd((string) $v), $dec);
    }
}
