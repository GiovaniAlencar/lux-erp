<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NaturezaOperacao;
use App\Models\ConfigNota;
use App\Models\Cidade;
use App\Models\Certificado;
use App\Models\Tributacao;
use App\Services\NFService;
use App\Utils\UploadUtil;
use NFePHP\Common\Certificate;
use Illuminate\Support\Facades\DB;

class ConfigNotaController extends Controller
{
    protected $util;

    public function __construct(UploadUtil $util)
    {
        $this->util = $util;
    }

    public function certificadosFresh()
    {
        $certificados = DB::table('certificados')->get();
        foreach ($certificados as $c) {
            $config = ConfigNota::find($c->empresa_id);
            if ($config) {
                $config->senha = $c->senha;
                $config->arquivo = $c->arquivo;
                $config->save();
            }
        }
    }

    public function removeSenha($id)
    {
        $config = ConfigNota::find($id);
        $config->senha_remover = '';
        $config->save();
        session()->flash("flash_sucesso", "Senha removida!");
        return redirect()->route('configNF.index');
    }

    public function removeLogo()
    {
        $item = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();
        $item->logo = '';
        $item->save();
        session()->flash("flash_sucesso", "Logo removida!");
        return redirect()->back();
    }

    public function index(Request $request)
    {
        $item = ConfigNota::where('empresa_id', $request->empresa_id)
            ->first();
        $naturezas = NaturezaOperacao::where('empresa_id', request()->empresa_id)
            ->get();
        $tiposPagamento = ConfigNota::tiposPagamento();
        $tiposFrete = ConfigNota::tiposFrete();
        $listaCSTCSOSN = ConfigNota::listaCST();
        $listaCSTPISCOFINS = ConfigNota::listaCST_PIS_COFINS();
        $listaCSTIPI = ConfigNota::listaCST_IPI();
        $config = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();
        $cUF = ConfigNota::estados();
        $infoCertificado = null;
        if ($item != null && $item->arquivo != null) {
            $infoCertificado = $this->getInfoCertificado($item);
        }
        $soapDesativado = !extension_loaded('soap');
        $cidades = Cidade::all();
        $tributacao = Tributacao::where('empresa_id', $request->empresa_id)->first();
        $regimes = Tributacao::regimes();
        $statusNfe = $this->statusNfe($item, $infoCertificado, $tributacao, $soapDesativado);
        return view(
            'config_nota/index',
            compact(
                'config',
                'naturezas',
                'tiposPagamento',
                'tiposFrete',
                'infoCertificado',
                'soapDesativado',
                'listaCSTCSOSN',
                'listaCSTPISCOFINS',
                'listaCSTIPI',
                'cUF',
                'cidades',
                'item',
                'tributacao',
                'regimes',
                'statusNfe'
            )
        );
    }

    private function getInfoCertificado($item)
    {
        try {
            $infoCertificado = Certificate::readPfx($item->arquivo, $item->senha);
            $publicKey = $infoCertificado->publicKey;
            $inicio =  $publicKey->validFrom->format('Y-m-d H:i:s');
            $expiracao =  $publicKey->validTo->format('Y-m-d H:i:s');
            return [
                'serial' => $publicKey->serialNumber,
                'inicio' => \Carbon\Carbon::parse($inicio)->format('d-m-Y H:i'),
                'expiracao' => \Carbon\Carbon::parse($expiracao)->format('d-m-Y H:i'),
                'id' => $publicKey->commonName,
                'cnpj' => preg_replace('/\D/', '', (string) $publicKey->cnpj()),
                'expira_em' => $expiracao,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function store(Request $request)
    {
        // NFC-e / CT-e / CSC não são usados pela LUX: aceita vazio
        $request->merge([
            'numero_serie_nfce' => $request->numero_serie_nfce ?: 1,
            'ultimo_numero_nfce' => $request->ultimo_numero_nfce ?: 0,
            'numero_serie_cte' => $request->numero_serie_cte ?: 1,
            'ultimo_numero_cte' => $request->ultimo_numero_cte ?: 0,
            'csc' => $request->csc ?? '',
            'csc_id' => $request->csc_id ?? '',
        ]);
        $this->_validate($request);
        $this->salvarRegime($request);
        $item = ConfigNota::where('empresa_id', $request->empresa_id)
            ->first();
        if (!__valida_objeto($item)) {
            abort(403);
        }
        try {
            if ($item == null) {
                $file_name = '';
                if ($request->hasFile('image')) {
                    $file_name = $this->util->uploadImage($request, '/configEmitente');
                }
                $request->merge([
                    'pais' => $request->pais ?? '',
                    'cUF' => $request->cUF ?? '',
                    'campo_obs_pedido' => $request->campo_obs_pedido ?? '',
                    'campo_obs_nfe' => $request->campo_obs_nfe ?? '',
                    'certificado_a3' => $request->certificado_a3 ?? 0,
                    'inscricao_municipal' => $request->inscricao_minicipal ?? '',
                    'complemento' => $request->complemento ?? '',
                    'token_ibpt' => $request->token_ibpt ?? '',
                    'percentual_lucro_padrao' => $request->percentual_lucro_padrao ?? 0,
                    'validade_orcamento' => $request->validade_orcamento ?? 0,
                    'casas_decimais' => $request->casas_decimais ?? 2,
                    'logo' => $file_name,
                    'nat_op_padrao' => $request->nat_op_padrao ?? 0,
                    'parcelamento_maximo' => $request->parcelamento_maximo ?? 12,
                    'sobrescrita_csonn_consumidor_final' => $request->sobrescrita_csonn_consumidor_final ?? '',
                    'senha_remover' => $request->senha_remover ? md5($request->senha_remover) : ''
                ]);
                if ($request->hasFile('certificado')) {
                    $file = $request->file('certificado');
                    $temp = file_get_contents($file);
                    $extensao = $file->getClientOriginalExtension();
                    $request->merge([
                        'arquivo' => $temp
                    ]);
                    $cnpj = preg_replace('/[^0-9]/', '', $request->cnpj);
                    $fileName = "$cnpj.$extensao";
                    if (!is_dir(public_path('certificados'))) {
                        mkdir(public_path('certificados'), 0777, true);
                    }
                    if (env("CERTIFICADO_ARQUIVO") == 1) {
                        $file->move(public_path('certificados'), $fileName);
                    }
                }
                ConfigNota::create($request->all());
                session()->flash("flash_sucesso", "Cadastrado com sucesso");
            } else {
                $file_name = '';
                if ($request->hasFile('image')) {
                    $this->util->unlinkImage($item, '/configEmitente');
                    $file_name = $this->util->uploadImage($request, '/configEmitente');
                };
                $request->merge([
                    'pais' => $request->pais ?? '',
                    'cUF' => $request->cUF ?? '',
                    'campo_obs_pedido' => $request->campo_obs_pedido ?? '',
                    'campo_obs_nfe' => $request->campo_obs_nfe ?? '',
                    'certificado_a3' => $request->certificado_a3 ?? 0,
                    'inscricao_municipal' => $request->inscricao_minicipal ?? '',
                    'complemento' => $request->complemento ?? '',
                    'token_ibpt' => $request->token_ibpt ?? '',
                    'percentual_lucro_padrao' => $request->percentual_lucro_padrao ?? 0,
                    'validade_orcamento' => $request->validade_orcamento ?? 0,
                    'casas_decimais' => $request->casas_decimais ?? 2,
                    'parcelamento_maximo' => $request->parcelamento_maximo ?? 12,
                    'logo' => $file_name,
                    'sobrescrita_csonn_consumidor_final' => $request->sobrescrita_csonn_consumidor_final ?? '',
                    'senha_remover' => $request->senha_remover ? md5($request->senha_remover) : ''
                ]);
                if ($request->hasFile('certificado')) {
                    $file = $request->file('certificado');
                    $temp = file_get_contents($file);
                    $extensao = $file->getClientOriginalExtension();
                    $request->merge([
                        'arquivo' => $temp
                    ]);
                    $cnpj = preg_replace('/[^0-9]/', '', $item->cnpj);
                    $fileName = "$cnpj.$extensao";
                    if (!is_dir(public_path('certificados'))) {
                        mkdir(public_path('certificados'), 0777, true);
                    }
                    if (env("CERTIFICADO_ARQUIVO") == 1) {
                        $file->move(public_path('certificados'), $fileName);
                    }
                }
                $item->fill($request->all())->save();
                session()->flash("flash_sucesso", "Emitente atualizado!");
            }

            $value = session('user_logged');
            $value['ambiente'] = $request->ambiente == 1 ? 'Produção' : 'Homologação';
            session()->put('user_logged', $value);
        } catch (\Exception $e) {
            // echo $e->getMessage() . '<br>' . $e->getLine();
            // die;
            session()->flash("flash_erro", "Algo deu Errado" . $e->getMessage());
            __saveLogError($e, request()->empresa_id);
        }
        return redirect()->route('configNF.index');
    }

    private function salvarRegime(Request $request): void
    {
        if ($request->regime_tributario === null || $request->regime_tributario === '') {
            return;
        }
        $trib = Tributacao::where('empresa_id', $request->empresa_id)->first();
        if (!$trib) {
            Tributacao::create([
                'empresa_id' => $request->empresa_id,
                'icms' => 0, 'pis' => 0, 'cofins' => 0, 'ipi' => 0, 'perc_ap_cred' => 0,
                'ncm_padrao' => '', 'link_nfse' => '',
                'regime' => (string) (int) $request->regime_tributario,
            ]);
            return;
        }
        $trib->regime = (string) (int) $request->regime_tributario;
        $trib->save();
    }

    /** Checklist do que a NF-e precisa para funcionar (mostrado na tela do emitente). */
    private function statusNfe($item, $infoCertificado, $tributacao, bool $soapDesativado): array
    {
        $itens = [];
        $add = function (string $nome, bool $ok, string $detalhe) use (&$itens) {
            $itens[] = compact('nome', 'ok', 'detalhe');
        };

        $add('PHP do site', true, 'versão ' . PHP_VERSION . ' · ' . (php_ini_loaded_file() ?: 'sem php.ini carregado'));
        $add('Extensão SOAP do PHP', !$soapDesativado, $soapDesativado ? 'Ative a extensão soap no PHP' : 'ativa');
        $add('Extensão cURL do PHP', extension_loaded('curl'), extension_loaded('curl') ? 'ativa' : 'DESATIVADA — ative a curl no php.ini acima');
        $add('Extensão OpenSSL do PHP', extension_loaded('openssl'), extension_loaded('openssl') ? 'ativa' : 'DESATIVADA — ative a openssl no php.ini acima');

        if (!$item) {
            $add('Emitente', false, 'Cadastre o emitente abaixo');
            return $itens;
        }

        $cnpjEmit = preg_replace('/\D/', '', (string) $item->cnpj);
        if (empty($infoCertificado)) {
            $add('Certificado digital A1', false, $item->arquivo ? 'Não foi possível ler (senha errada?)' : 'Envie o arquivo .pfx e a senha');
        } else {
            $dias = (int) floor((strtotime($infoCertificado['expira_em']) - time()) / 86400);
            $add('Certificado digital A1', $dias > 0, $dias > 0 ? "válido até {$infoCertificado['expiracao']} ({$dias} dias)" : 'VENCIDO em ' . $infoCertificado['expiracao']);
            $cnpjCert = $infoCertificado['cnpj'] ?? '';
            if ($cnpjCert !== '') {
                $add('CNPJ do certificado = CNPJ do emitente', $cnpjCert === $cnpjEmit, $cnpjCert === $cnpjEmit ? $cnpjCert : "certificado {$cnpjCert} × emitente {$cnpjEmit}");
            }
        }

        $cidade = $item->cidade ?? null;
        $add('Cidade do emitente (código IBGE)', $cidade && strlen((string) $cidade->codigo) === 7, $cidade ? "{$cidade->nome}/{$cidade->uf} · {$cidade->codigo}" : 'Selecione a cidade');
        $add('Inscrição estadual', preg_replace('/\D/', '', (string) $item->ie) !== '', $item->ie ?: 'Informe a IE');

        $regime = $tributacao ? (Tributacao::regimes()[(int) $tributacao->regime] ?? $tributacao->regime) : null;
        $add('Regime tributário', $tributacao !== null, $regime ?? 'Selecione abaixo');

        $amb = (int) $item->ambiente === 1 ? 'PRODUÇÃO (nota com valor fiscal)' : 'Homologação (teste, sem valor fiscal)';
        $add('Ambiente', true, $amb);
        $add('Série / próxima NF-e', (int) $item->numero_serie_nfe > 0,
            'série ' . (int) $item->numero_serie_nfe . ' · próxima nº ' . ((int) $item->ultimo_numero_nfe + 1));

        return $itens;
    }

    /** Testa a comunicação com a SEFAZ usando o certificado (consulta status do serviço). */
    public function statusSefaz(Request $request)
    {
        $config = ConfigNota::where('empresa_id', $request->empresa_id)->first();
        if (!$config || !$config->arquivo) {
            return response()->json(['ok' => false, 'msg' => 'Cadastre o emitente e o certificado primeiro.'], 422);
        }
        foreach (['soap', 'curl', 'openssl'] as $ext) {
            if (!extension_loaded($ext)) {
                return response()->json(['ok' => false, 'msg' => "Extensão {$ext} do PHP desativada (PHP " . PHP_VERSION . ', ' . (php_ini_loaded_file() ?: 'sem php.ini') . ').'], 422);
            }
        }
        try {
            $nfe = new NFService([
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
            ob_start();
            $r = $nfe->consultaStatus((int) $config->ambiente, $config->cidade->uf);
            $eco = trim((string) ob_get_clean());
            if (!is_array($r)) {
                return response()->json(['ok' => false, 'msg' => $eco ?: 'Sem resposta da SEFAZ.'], 502);
            }
            $cStat = (string) ($r['cStat'] ?? '');
            return response()->json([
                'ok' => $cStat === '107',
                'msg' => '[' . $cStat . '] ' . ($r['xMotivo'] ?? ''),
                'ambiente' => (int) $config->ambiente === 1 ? 'Produção' : 'Homologação',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    private function _validate(Request $request)
    {
        $rules = [
            'cnpj' => 'required',
            'razao_social' => 'required',
            'nome_fantasia' => 'required',
            'ie' => 'required',
            'logradouro' => 'required',
            'numero' => 'required',
            'bairro' => 'required',
            'cep' => 'required',
            'email' => 'required',
            'fone' => 'required',
            'numero_serie_nfe' => 'required|integer|min:1|max:999',
            'ultimo_numero_nfe' => 'required|integer|min:0',
            // 'numero_serie_mdfe' => 'required',
            // 'ultimo_numero_mdfe' => 'required',
            'csc_id' => 'nullable|max:10',
            'cidade_id' => 'required',
        ];
        $message = [
            'cnpj.required' => 'Campo Obrigatório',
            'razao_social.required' => 'Campo Obrigatório',
            'nome_fantasia.required' => 'Campo Obrigatório',
            'ie.required' => 'Campo Obrigatório',
            'logradouro.required' => 'Campo Obrigatório',
            'numero.required' => 'Campo Obrigatório',
            'email.required' => 'Campo Obrigatório',
            'bairro.required' => 'Campo Obrigatório',
            'cep.required' => 'Campo Obrigatório',
            'fone.required' => 'Campo Obrigatório',
            'numero_serie_nfe.required' => 'Campo Obrigatório',
            'numero_serie_nfce.required' => 'Campo Obrigatório',
            'numero_serie_cte.required' => 'Campo Obrigatório',
            'numero_serie_mdfe.required' => 'Campo Obrigatório',
            'ultimo_numero_nfe.required' => 'Campo Obrigatório',
            'ultimo_numero_nfce.required' => 'Campo Obrigatório',
            'ultimo_numero_cte.required' => 'Campo Obrigatório',
            'ultimo_numero_mdfe.required' => 'Campo Obrigatório',
            'csc.required' => 'Campo Obrigatório',
            'csc_id.max' => 'Máximo de 10 caracteres.',
            'csc_id.required' => 'Campo Obrigatório',
            'cidade_id.required' => 'Campo Obrigatório'
        ];
        $this->validate($request, $rules, $message);
    }


    public function verificaSenha(Request $request)
    {
        $config = ConfigNota::where('senha_remover', md5($request->senha))
            ->where('empresa_id', $request->empresa_id)
            ->first();
        if ($config != null) {
            return response()->json("ok", 200);
        } else {
            return response()->json("", 401);
        }
    }


    public function deleteCertificado()
    {
        $item = ConfigNota::where('empresa_id', request()->empresa_id)
            ->first();
        try {
            $item->arquivo = '';
            $item->save();
            session()->flash("flash_sucesso", "Certificado Removido!");
        } catch (\Exception $e) {
        }

        return redirect()->route('configNF.index');
    }
}
