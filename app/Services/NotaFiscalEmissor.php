<?php

namespace App\Services;

use App\Helpers\Documento;
use App\Models\Cliente;
use App\Models\ConfigNota;
use App\Models\Frete;
use App\Models\ItemVenda;
use App\Models\NaturezaOperacao;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Tributacao;
use App\Models\Venda;
use App\Models\VendaNfeAdapter;
use App\Support\ProdutoFiscal;
use Illuminate\Support\Facades\DB;

/**
 * Emissão de NF-e da parte FISCAL de uma venda, usando o emissor existente (NFService).
 * Fluxo: rascunho (editável) -> validar -> transmitir -> autorizada | rejeitada.
 */
class NotaFiscalEmissor
{
    /** Cria (ou reaproveita) o rascunho de NF-e de uma venda com os itens fiscais. */
    public function rascunhoDaVenda(Venda $venda): NotaFiscal
    {
        $existente = NotaFiscal::where('venda_id', $venda->id)
            ->whereIn('status', ['rascunho', 'rejeitada', 'autorizada'])
            ->orderByDesc('id')
            ->first();
        if ($existente) {
            return $existente;
        }

        $venda->loadMissing('itens.produto');
        $fiscais = $venda->itens->filter(fn ($i) => (float) $i->qtd_fiscal > 0);
        if ($fiscais->isEmpty()) {
            throw new \RuntimeException('Esta venda não tem itens fiscais.');
        }

        $div = $venda->divisaoFiscal();
        $config = ConfigNota::where('empresa_id', $venda->empresa_id)->first();

        // Frete/desconto/acréscimo ficam na conta 2. Só vão para a NF se o pedido for todo fiscal.
        if (!$div['misto']) {
            $frete = (float) ($venda->frete ?? 0);
            $desconto = (float) ($venda->desconto ?? 0);
            $outros = (float) ($venda->acrescimo ?? 0);
        } else {
            $frete = 0.0;
            $outros = 0.0;
            // desconto maior que a conta 2 inteira: o excedente desconta da NF
            $desconto = max(0, round($div['itens_fiscal'] - $div['fiscal'], 2));
        }

        return DB::transaction(function () use ($venda, $fiscais, $config, $frete, $desconto, $outros) {
            $nota = NotaFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'venda_id' => $venda->id,
                'cliente_id' => $venda->cliente_id,
                'natureza_id' => $venda->natureza_id ?: optional($config)->nat_op_padrao,
                'usuario_id' => get_id_user(),
                'status' => 'rascunho',
                'ambiente' => (int) optional($config)->ambiente ?: 2,
                'data_emissao' => now(),
                'valor_frete' => $frete,
                'valor_desconto' => $desconto,
                'valor_outros' => $outros,
                'tipo_pagamento' => $this->pagamentoDaVenda($venda),
                'info_complementar' => '',
            ]);
            foreach ($fiscais as $it) {
                NotaFiscalItem::create([
                    'nota_fiscal_id' => $nota->id,
                    'produto_id' => $it->produto_id,
                    'item_venda_id' => $it->id,
                    'descricao' => mb_substr((string) optional($it->produto)->nome, 0, 150),
                    'quantidade' => (float) $it->qtd_fiscal,
                    'valor_unitario' => (float) $it->valor,
                ]);
            }
            $nota->load('itens');
            $nota->recalcularTotais();
            $nota->save();
            return $nota;
        });
    }

    private function pagamentoDaVenda(Venda $venda): string
    {
        $tp = (string) ($venda->tipo_pagamento ?? '');
        return array_key_exists($tp, NotaFiscal::PAGAMENTOS) ? $tp : '17';
    }

    /**
     * Confere tudo antes de transmitir.
     * @return array<string, string[]> grupo => mensagens (vazio = pode emitir)
     */
    public function validar(NotaFiscal $nota): array
    {
        $nota->loadMissing(['itens.produto', 'cliente.cidade', 'natureza']);
        $erros = ['Emitente' => [], 'Destinatário' => [], 'Itens' => [], 'Nota' => []];

        // Emitente
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        if (!$config) {
            $erros['Emitente'][] = 'Emitente não configurado (Configurações › Configurar Emitente).';
        } else {
            if (!$config->arquivo) {
                $erros['Emitente'][] = 'Certificado digital não enviado.';
            }
            if (!$config->cidade || strlen((string) $config->cidade->codigo) !== 7) {
                $erros['Emitente'][] = 'Cidade do emitente sem código IBGE.';
            }
            if ((int) $config->numero_serie_nfe <= 0) {
                $erros['Emitente'][] = 'Série da NF-e não configurada.';
            }
        }
        if (!Tributacao::where('empresa_id', $nota->empresa_id)->exists()) {
            $erros['Emitente'][] = 'Regime tributário não definido (tela do emitente).';
        }
        if (!extension_loaded('soap') || !extension_loaded('curl')) {
            $erros['Emitente'][] = 'Extensões soap/curl do PHP desativadas.';
        }

        // Destinatário
        $c = $nota->cliente;
        if (!$c) {
            $erros['Destinatário'][] = 'Cliente não encontrado.';
        } else {
            $doc = Documento::digitos($c->cpf_cnpj);
            if (!Documento::valido($c->cpf_cnpj)) {
                $erros['Destinatário'][] = 'CPF/CNPJ inválido ou vazio (' . ($c->cpf_cnpj ?: 'vazio') . ').';
            }
            $ie = strtoupper(trim((string) $c->ie_rg));
            $ieDig = preg_replace('/\D/', '', $ie);
            if (strlen($doc) === 14) {
                // CNPJ: IE obrigatória (número ou ISENTO)
                if ($ie === '') {
                    $erros['Destinatário'][] = 'Cliente com CNPJ sem Inscrição Estadual: informe o número da IE ou "ISENTO" no cadastro.';
                } elseif ($ie !== 'ISENTO' && (strlen($ieDig) < 2 || strlen($ieDig) > 14)) {
                    $erros['Destinatário'][] = 'Inscrição Estadual inválida: ' . $c->ie_rg . ' (use só números ou "ISENTO").';
                }
            } elseif ($c->contribuinte) {
                // CPF marcado como contribuinte (produtor rural etc.)
                if ($ieDig === '' || $ie === 'ISENTO') {
                    $erros['Destinatário'][] = 'Pessoa física marcada como contribuinte precisa de IE (ou desmarque "Contribuinte").';
                }
            }
            if ((int) ($c->cod_pais ?: 1058) === 1058) {
                if (trim((string) $c->rua) === '') {
                    $erros['Destinatário'][] = 'Endereço (rua) não preenchido.';
                }
                if (trim((string) $c->numero) === '') {
                    $erros['Destinatário'][] = 'Número do endereço não preenchido (use "SN" se não tiver).';
                }
                if (trim((string) $c->bairro) === '') {
                    $erros['Destinatário'][] = 'Bairro não preenchido.';
                }
                if (strlen(preg_replace('/\D/', '', (string) $c->cep)) !== 8) {
                    $erros['Destinatário'][] = 'CEP inválido ou vazio.';
                }
                if (!$c->cidade) {
                    $erros['Destinatário'][] = 'Cidade não preenchida (busque pelo CEP no cadastro do cliente).';
                } elseif (strlen((string) $c->cidade->codigo) !== 7) {
                    $erros['Destinatário'][] = 'Cidade "' . $c->cidade->nome . '" sem código IBGE.';
                }
            }
        }

        // Itens
        if ($nota->itens->isEmpty()) {
            $erros['Itens'][] = 'A nota não tem itens.';
        }
        foreach ($nota->itens as $it) {
            $nome = $it->descricao ?: ('#' . $it->produto_id);
            if (!$it->produto) {
                $erros['Itens'][] = $nome . ': produto não existe mais.';
                continue;
            }
            if ((float) $it->quantidade <= 0) {
                $erros['Itens'][] = $nome . ': quantidade deve ser maior que zero.';
            }
            if ((float) $it->valor_unitario <= 0) {
                $erros['Itens'][] = $nome . ': valor unitário deve ser maior que zero.';
            }
            if ($it->item_venda_id) {
                $iv = \App\Models\ItemVenda::find($it->item_venda_id);
                if ($iv && (float) $it->quantidade > (float) $iv->qtd_fiscal + 0.0005) {
                    $erros['Itens'][] = $nome . ': quantidade na NF (' . (float) $it->quantidade . ') maior que a parte fiscal do pedido ('
                        . (float) $iv->qtd_fiscal . ' un com nota).';
                }
            }
            $p = $it->produto;
            $dados = $p->only(['NCM', 'CEST', 'origem', 'CST_CSOSN', 'CFOP_saida_estadual', 'CFOP_saida_inter_estadual', 'CST_PIS', 'CST_COFINS', 'unidade_venda']);
            foreach (ProdutoFiscal::erros($dados) as $msg) {
                $erros['Itens'][] = $nome . ' (ID ' . $p->id . '): ' . $msg;
            }
        }

        // Nota
        if (!$nota->natureza) {
            $erros['Nota'][] = 'Natureza de operação não definida.';
        }
        if (!$nota->data_emissao) {
            $erros['Nota'][] = 'Informe a data de emissão.';
        } else {
            if ($nota->data_emissao->gt(now()->addMinutes(5))) {
                $erros['Nota'][] = 'Data de emissão no futuro.';
            }
            if ($nota->data_emissao->lt(now()->subDays(30))) {
                $erros['Nota'][] = 'Data de emissão com mais de 30 dias: a SEFAZ rejeita.';
            }
        }
        if ((float) $nota->valor_total <= 0) {
            $erros['Nota'][] = 'Valor total da nota deve ser maior que zero.';
        }
        if ((float) $nota->valor_desconto > (float) $nota->valor_produtos) {
            $erros['Nota'][] = 'Desconto maior que o valor dos produtos.';
        }

        return array_filter($erros);
    }

    /**
     * Transmite a nota. Retorna ['ok' => bool, 'msg' => string].
     */
    public function transmitir(NotaFiscal $nota): array
    {
        if (!$nota->editavel()) {
            return ['ok' => false, 'msg' => 'Esta nota não pode mais ser transmitida (status: ' . $nota->status . ').'];
        }
        $erros = $this->validar($nota);
        if (!empty($erros)) {
            return ['ok' => false, 'msg' => 'Corrija os pendentes antes de transmitir.', 'erros' => $erros];
        }

        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $nfe = $this->servico($config);
        $nfe->dataEmissao = $nota->data_emissao->format('Y-m-d\TH:i:sP');

        $venda = $this->vendaAdaptada($nota);
        $gerada = $nfe->gerarNFe($venda);
        if (isset($gerada['erros_xml'])) {
            $msg = 'Erro ao montar o XML: ' . implode(' | ', array_map('strval', (array) $gerada['erros_xml']));
            $nota->update(['ultimo_retorno' => $msg]);
            return ['ok' => false, 'msg' => $msg];
        }

        $assinado = $nfe->sign($gerada['xml']);
        // guarda o XML assinado: se a SEFAZ demorar, dá para consultar e montar o XML autorizado depois
        $this->salvarAssinado($gerada['chave'], $assinado);
        $res = $nfe->transmitir($assinado, $gerada['chave']);

        $nota->ambiente = (int) $config->ambiente;
        $nota->serie = (int) $config->numero_serie_nfe;
        $nota->numero = (int) $gerada['nNf'];
        $nota->chave = $gerada['chave'];

        if ((int) ($res['erro'] ?? 1) === 0) {
            $nota->status = 'autorizada';
            $nota->autorizada_em = now();
            $nota->protocolo = $this->protocoloDoXml(public_path('xml_nfe/' . $gerada['chave'] . '.xml'));
            $nota->ultimo_retorno = 'Autorizada. Recibo ' . ($res['success'] ?? '');
            $nota->save();

            // numeração usada
            $config->ultimo_numero_nfe = max((int) $config->ultimo_numero_nfe, (int) $gerada['nNf']);
            $config->save();

            $this->marcarVendaEmitida($nota);

            return ['ok' => true, 'msg' => 'NF-e ' . $nota->numero . ' autorizada.'];
        }

        $texto = $this->textoErro($res['error'] ?? 'Erro desconhecido');
        $cStat = $this->cStatDoErro($res['error'] ?? null);

        // 103/105 = lote ainda em processamento; 204/539 = duplicidade (pode já ter sido autorizada)
        if (in_array($cStat, ['103', '105', '204', '539'], true) || stripos($texto, 'processamento') !== false) {
            $nota->status = 'rascunho';
            $nota->ultimo_retorno = $texto . ' — a nota pode ter sido autorizada: use "Consultar situação na SEFAZ" antes de transmitir de novo.';
            $nota->save();
            return ['ok' => false, 'msg' => $nota->ultimo_retorno];
        }

        $nota->status = 'rejeitada';
        $nota->ultimo_retorno = $texto;
        $nota->save();

        return ['ok' => false, 'msg' => $nota->ultimo_retorno];
    }

    /**
     * Consulta a chave na SEFAZ. Se estiver autorizada, monta o XML autorizado e marca a nota.
     */
    public function consultar(NotaFiscal $nota): array
    {
        if (!$nota->chave) {
            return ['ok' => false, 'msg' => 'A nota ainda não foi transmitida (sem chave).'];
        }
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $nfe = $this->servico($config);

        $v = new VendaNfeAdapter();
        $v->forceFill(['chave' => $nota->chave]);
        ob_start();
        $arr = $nfe->consultar($v);
        $eco = trim((string) ob_get_clean());
        if (!is_array($arr)) {
            return ['ok' => false, 'msg' => $eco ?: 'Sem resposta da SEFAZ.'];
        }

        $cStat = (string) ($arr['cStat'] ?? '');
        $motivo = '[' . $cStat . '] ' . ($arr['xMotivo'] ?? '');

        if (in_array($cStat, ['100', '150'], true) && $nota->status !== 'autorizada') {
            $assinado = $this->lerAssinado($nota->chave);
            if (!$assinado) {
                $nota->update(['ultimo_retorno' => $motivo . ' — autorizada na SEFAZ, mas o XML assinado não foi encontrado.']);
                return ['ok' => false, 'msg' => $nota->ultimo_retorno];
            }
            $xml = \NFePHP\NFe\Complements::toAuthorize($assinado, $nfe->ultimaResposta);
            file_put_contents(public_path('xml_nfe/') . $nota->chave . '.xml', $xml);
            $nota->status = 'autorizada';
            $nota->autorizada_em = now();
            $nota->protocolo = $arr['protNFe']['infProt']['nProt'] ?? $this->protocoloDoXml(public_path('xml_nfe/' . $nota->chave . '.xml'));
            $nota->ultimo_retorno = 'Autorizada (confirmada por consulta).';
            $nota->save();
            $config->ultimo_numero_nfe = max((int) $config->ultimo_numero_nfe, (int) $nota->numero);
            $config->save();
            $this->marcarVendaEmitida($nota);
            return ['ok' => true, 'msg' => 'NF-e ' . $nota->numero . ' está autorizada.'];
        }

        $nota->update(['ultimo_retorno' => 'Consulta: ' . $motivo]);
        return ['ok' => in_array($cStat, ['100', '150'], true), 'msg' => 'Situação na SEFAZ: ' . $motivo];
    }

    /** Cancela a NF-e autorizada (evento 110111). */
    public function cancelar(NotaFiscal $nota, string $motivo): array
    {
        $motivo = trim(preg_replace('/\s+/', ' ', $motivo));
        if ($nota->status !== 'autorizada') {
            return ['ok' => false, 'msg' => 'Só é possível cancelar nota autorizada.'];
        }
        if (mb_strlen($motivo) < 15 || mb_strlen($motivo) > 255) {
            return ['ok' => false, 'msg' => 'A justificativa deve ter entre 15 e 255 caracteres.'];
        }
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $nfe = $this->servico($config);
        $v = new VendaNfeAdapter();
        $v->forceFill(['chave' => $nota->chave]);

        $res = $nfe->cancelar($v, $this->semAcento($motivo));
        [$ok, $texto] = $this->resultadoEvento($res);
        if (!$ok) {
            $nota->update(['ultimo_retorno' => 'Cancelamento não realizado: ' . $texto]);
            return ['ok' => false, 'msg' => 'Cancelamento não realizado: ' . $texto];
        }

        $nota->status = 'cancelada';
        $nota->cancelada_em = now();
        $nota->motivo_cancelamento = $motivo;
        $nota->ultimo_retorno = 'Cancelada: ' . $texto;
        $nota->save();

        // pedido volta a ficar com NF pendente
        if ($nota->venda_id && ($venda = Venda::find($nota->venda_id))) {
            if ((string) $venda->nf_externa_numero === (string) $nota->numero) {
                $venda->nf_externa_status = 'pendente';
                $venda->nf_externa_numero = null;
                $venda->nf_externa_valor = null;
                $venda->nf_externa_em = null;
                $venda->nf_externa_usuario_id = get_id_user();
                $venda->save();
            }
        }

        return ['ok' => true, 'msg' => 'NF-e ' . $nota->numero . ' cancelada. ' . $texto];
    }

    /** Carta de correção eletrônica (evento 110110). */
    public function cartaCorrecao(NotaFiscal $nota, string $texto): array
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if ($nota->status !== 'autorizada') {
            return ['ok' => false, 'msg' => 'Carta de correção só para nota autorizada.'];
        }
        if (mb_strlen($texto) < 15 || mb_strlen($texto) > 1000) {
            return ['ok' => false, 'msg' => 'A correção deve ter entre 15 e 1000 caracteres.'];
        }
        if ((int) $nota->sequencia_cce >= 20) {
            return ['ok' => false, 'msg' => 'Limite de 20 cartas de correção atingido.'];
        }
        $config = ConfigNota::where('empresa_id', $nota->empresa_id)->first();
        $nfe = $this->servico($config);
        $v = new VendaNfeAdapter();
        $v->forceFill(['chave' => $nota->chave, 'sequencia_cce' => (int) $nota->sequencia_cce]);

        $res = $nfe->cartaCorrecao($v, $this->semAcento($texto));
        [$ok, $msg] = $this->resultadoEvento($res);
        if (!$ok) {
            $nota->update(['ultimo_retorno' => 'Carta de correção não registrada: ' . $msg]);
            return ['ok' => false, 'msg' => 'Carta de correção não registrada: ' . $msg];
        }
        $nota->sequencia_cce = (int) $nota->sequencia_cce + 1;
        $nota->ultimo_retorno = 'CC-e nº ' . $nota->sequencia_cce . ' registrada: ' . $msg;
        $nota->save();

        return ['ok' => true, 'msg' => 'Carta de correção nº ' . $nota->sequencia_cce . ' registrada.'];
    }

    /** Interpreta o retorno de evento do NFService (array ok | ['erro'=>true,...] | null). */
    private function resultadoEvento($res): array
    {
        if ($res === null) {
            return [false, 'SEFAZ não processou o lote do evento. Tente novamente.'];
        }
        if (!is_array($res)) {
            return [false, (string) $res];
        }
        if (!empty($res['erro'])) {
            $d = $res['data'] ?? '';
            if (is_array($d)) {
                $inf = $d['retEvento']['infEvento'] ?? $d;
                return [false, '[' . ($inf['cStat'] ?? '?') . '] ' . ($inf['xMotivo'] ?? json_encode($d, JSON_UNESCAPED_UNICODE))];
            }
            return [false, (string) $d];
        }
        $inf = $res['retEvento']['infEvento'] ?? [];
        $cStat = (string) ($inf['cStat'] ?? '');
        $ok = in_array($cStat, ['135', '136', '155'], true);
        return [$ok, '[' . $cStat . '] ' . ($inf['xMotivo'] ?? '') . (isset($inf['nProt']) ? ' · protocolo ' . $inf['nProt'] : '')];
    }

    private function semAcento(string $s): string
    {
        $c = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;
        return $c !== false ? $c : $s;
    }

    private function servico(ConfigNota $config): NFService
    {
        foreach (['xml_nfe', 'xml_nfe_cancelada', 'xml_nfe_correcao'] as $d) {
            if (!is_dir(public_path($d))) {
                @mkdir(public_path($d), 0777, true);
            }
        }
        return new NFService([
            'atualizacao' => date('Y-m-d h:i:s'),
            'tpAmb' => (int) $config->ambiente,
            'razaosocial' => $config->razao_social,
            'siglaUF' => $config->cidade->uf,
            'cnpj' => preg_replace('/\D/', '', $config->cnpj),
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => 'AAAAAAA',
            'CSC' => $config->csc,
            'CSCid' => $config->csc_id,
        ], $config);
    }

    private function salvarAssinado(string $chave, string $xml): void
    {
        $dir = storage_path('app/nfe_assinadas');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/' . $chave . '.xml', $xml);
    }

    private function lerAssinado(string $chave): ?string
    {
        $p = storage_path('app/nfe_assinadas/' . $chave . '.xml');
        return file_exists($p) ? file_get_contents($p) : null;
    }

    private function cStatDoErro($erro): string
    {
        if (is_array($erro)) {
            return (string) ($erro['protNFe']['infProt']['cStat'] ?? $erro['cStat'] ?? '');
        }
        if (is_string($erro) && preg_match('/\[(\d{3})\]/', $erro, $m)) {
            return $m[1];
        }
        return '';
    }

    /** Monta um objeto Venda (não salvo) só com os itens da nota, no formato que o NFService espera. */
    public function vendaAdaptada(NotaFiscal $nota): Venda
    {
        $nota->loadMissing(['itens.produto', 'cliente.cidade', 'natureza']);

        $v = new VendaNfeAdapter();
        $v->forceFill([
            'empresa_id' => $nota->empresa_id,
            'cliente_id' => $nota->cliente_id,
            'natureza_id' => $nota->natureza_id,
            'valor_total' => (float) $nota->valor_produtos,
            'desconto' => (float) $nota->valor_desconto,
            'acrescimo' => (float) $nota->valor_outros,
            'tipo_pagamento' => $nota->tipo_pagamento,
            'forma_pagamento' => 'a_vista',
            'observacao' => (string) $nota->info_complementar,
            'pedido_ecommerce_id' => 0,
            'pedido_nuvemshop_id' => 0,
            'bandeira_cartao' => '99',
            'cnpj_cartao' => '',
            'cAut_cartao' => '',
            'descricao_pag_outros' => '',
            'sequencia_cce' => (int) $nota->sequencia_cce,
            'chave' => (string) $nota->chave,
        ]);

        $itens = $nota->itens->map(function (NotaFiscalItem $it) use ($nota) {
            $iv = new ItemVenda();
            $iv->forceFill([
                'produto_id' => $it->produto_id,
                'quantidade' => (float) $it->quantidade,
                'quantidade_dimensao' => 1,
                'valor' => (float) $it->valor_unitario,
                'x_pedido' => '',
                'num_item_pedido' => '',
            ]);
            $iv->setRelation('produto', $it->produto);
            return $iv;
        })->values();

        $frete = null;
        if ((float) $nota->valor_frete > 0) {
            $frete = new Frete();
            $frete->forceFill([
                'valor' => (float) $nota->valor_frete,
                'tipo' => '0', // por conta do emitente
                'placa' => '', 'uf' => '', 'qtdVolumes' => 0, 'peso_liquido' => 0, 'peso_bruto' => 0,
                'especie' => '', 'numeracaoVolumes' => '',
            ]);
        }

        $v->setRelation('itens', $itens);
        $cliente = clone $nota->cliente; // só em memória, não salva
        if (!$cliente->cod_pais) {
            $cliente->cod_pais = 1058;
        }
        // Indicador da IE do destinatário a partir do CNPJ + IE:
        // CNPJ com IE numérica = contribuinte (1); CNPJ "ISENTO" = contribuinte isento (2); sem IE = não contribuinte (9)
        if (strlen(Documento::digitos($cliente->cpf_cnpj)) === 14) {
            $ieCli = strtoupper(trim((string) $cliente->ie_rg));
            if ($ieCli === 'ISENTO') {
                $cliente->contribuinte = 1;
                $cliente->ie_rg = 'ISENTO';
            } elseif (preg_replace('/\D/', '', $ieCli) !== '') {
                $cliente->contribuinte = 1;
            } else {
                $cliente->contribuinte = 0;
            }
        }
        $v->setRelation('cliente', $cliente);
        $v->setRelation('natureza', $nota->natureza);
        $v->setRelation('referencias', collect());
        $v->setRelation('duplicatas', collect());
        $v->setRelation('transportadora', null);
        $v->setRelation('frete', $frete);

        return $v;
    }

    private function marcarVendaEmitida(NotaFiscal $nota): void
    {
        if (!$nota->venda_id) {
            return;
        }
        $venda = Venda::with('itens')->find($nota->venda_id);
        if (!$venda) {
            return;
        }
        $venda->nf_externa_status = 'emitida';
        $venda->nf_externa_numero = (string) $nota->numero;
        $venda->nf_externa_valor = $venda->divisaoFiscal()['fiscal'];
        $venda->nf_externa_em = now();
        $venda->nf_externa_usuario_id = get_id_user();
        $venda->save();
    }

    private function protocoloDoXml(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        if (preg_match('/<nProt>(\d+)<\/nProt>/', (string) file_get_contents($path), $m)) {
            return $m[1];
        }
        return null;
    }

    /** Transforma o retorno de erro (string ou array da SEFAZ) em texto legível. */
    private function textoErro($erro): string
    {
        if (is_string($erro)) {
            return $erro;
        }
        if (is_array($erro)) {
            $inf = $erro['protNFe']['infProt'] ?? null;
            if ($inf && isset($inf['cStat'])) {
                return '[' . $inf['cStat'] . '] ' . ($inf['xMotivo'] ?? '');
            }
            if (isset($erro['cStat'])) {
                return '[' . $erro['cStat'] . '] ' . ($erro['xMotivo'] ?? '');
            }
            return json_encode($erro, JSON_UNESCAPED_UNICODE);
        }
        return (string) $erro;
    }
}
