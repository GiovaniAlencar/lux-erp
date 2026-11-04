<?php

namespace App\Http\Controllers;

use App\Services\Crm\Crm;
use App\Services\Crm\Fila;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** CRM LUX (fase 2): visão geral, fila de contatos, histórico e configurações. Só administrador. */
class CrmController extends Controller
{
    private function exigir(): void
    {
        $u = session('user_logged');
        if (!($u['adm'] ?? false) && empty($u['super'])) {
            abort(403, 'Somente administradores acessam o CRM.');
        }
        if (!Crm::ativo()) {
            abort(redirect('/')->with('flash_erro', 'Rode o SQL 37 (CRM) para usar o CRM.'));
        }
    }

    private function usuarioId(): ?int
    {
        return (int) (session('user_logged')['id'] ?? 0) ?: null;
    }

    private function periodo(Request $request): array
    {
        $ini = $request->filled('inicio') ? Carbon::parse($request->inicio)->startOfDay() : now()->startOfMonth();
        $fim = $request->filled('fim') ? Carbon::parse($request->fim)->endOfDay() : now()->endOfDay();
        return [$ini, $fim];
    }

    public function index(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        $camps = Crm::campanhas($emp);
        [$ini, $fim] = $this->periodo($request);
        $campId = $request->filled('campanha') ? (int) $request->campanha : null;

        $ciclos = fn () => DB::table('crm_ciclos')->where('empresa_id', $emp)->when($campId, fn ($q) => $q->where('campanha_id', $campId));
        $envios = fn () => DB::table('crm_envios as e')->join('crm_ciclos as c', 'c.id', '=', 'e.ciclo_id')
            ->where('e.empresa_id', $emp)->when($campId, fn ($q) => $q->where('c.campanha_id', $campId));

        $elegiveis = collect(Fila::elegiveis($emp))->when($campId, fn ($c) => $c->where('campanha_id', $campId));
        $n = [
            'elegiveis' => $elegiveis->whereNull('fora')->count(),
            'aguardando' => $envios()->where('e.status', 'aguardando_aprovacao')->count(),
            'na_fila' => $envios()->where('e.status', 'pendente')->count(),
            'enviados' => $envios()->whereBetween('e.enviado_em', [$ini, $fim])->count(),
            'respostas' => $ciclos()->whereBetween('respondido_em', [$ini, $fim])->count(),
            'segunda' => $ciclos()->where('status', 'ativo')
                ->whereExists(fn ($q) => $q->from('crm_envios as e1')->whereColumn('e1.ciclo_id', 'crm_ciclos.id')->where('e1.tentativa', 1)->whereNotNull('e1.enviado_em'))
                ->whereNotExists(fn ($q) => $q->from('crm_envios as e2')->whereColumn('e2.ciclo_id', 'crm_ciclos.id')->where('e2.tentativa', 2))->count(),
            'assumidos' => $ciclos()->whereNotNull('atendente')->whereBetween('respondido_em', [$ini, $fim])->count(),
            'convertidos' => $ciclos()->where('status', 'convertido')->whereBetween('encerrado_em', [$ini, $fim])->count(),
            'faturamento' => (float) $ciclos()->where('status', 'convertido')->whereBetween('encerrado_em', [$ini, $fim])->sum('venda_valor'),
        ];
        $porCampanha = DB::table('crm_ciclos')->where('empresa_id', $emp)->whereBetween('created_at', [$ini, $fim])
            ->groupBy('campanha_id', 'status')->selectRaw('campanha_id, status, COUNT(*) n, SUM(venda_valor) v')->get()->groupBy('campanha_id');
        $foraPorMotivo = $elegiveis->whereNotNull('fora')->groupBy('fora')->map->count()->sortDesc();
        $cfg = Crm::config($emp);

        return view('crm.index', compact('camps', 'n', 'porCampanha', 'foraPorMotivo', 'cfg', 'ini', 'fim', 'campId'));
    }

    public function fila(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        $camps = Crm::campanhas($emp);
        $aba = in_array($request->aba, ['aguardando', 'aprovados', 'elegiveis', 'fora'], true) ? $request->aba : 'aguardando';
        $envios = collect();
        $elegiveis = collect();
        if (in_array($aba, ['aguardando', 'aprovados'], true)) {
            $envios = DB::table('crm_envios as e')->join('crm_ciclos as c', 'c.id', '=', 'e.ciclo_id')
                ->where('e.empresa_id', $emp)->where('e.status', $aba === 'aguardando' ? 'aguardando_aprovacao' : 'pendente')
                ->orderBy('e.agendado_para')->orderBy('e.id')
                ->get(['e.*', 'c.campanha_id', 'c.nome', 'c.cliente_id', 'c.motivo', 'c.contexto']);
        } else {
            $elegiveis = collect(Fila::elegiveis($emp))->filter(fn ($e) => $aba === 'fora' ? $e['fora'] !== null : $e['fora'] === null)->values();
        }
        $contagem = [
            'aguardando' => DB::table('crm_envios')->where('empresa_id', $emp)->where('status', 'aguardando_aprovacao')->count(),
            'aprovados' => DB::table('crm_envios')->where('empresa_id', $emp)->where('status', 'pendente')->count(),
        ];
        $cfg = Crm::config($emp);
        return view('crm.fila', compact('camps', 'aba', 'envios', 'elegiveis', 'contagem', 'cfg'));
    }

    public function gerar(Request $request)
    {
        $this->exigir();
        $n = Fila::gerar((int) $request->empresa_id, $this->usuarioId());
        return redirect()->route('crm.fila')->with($n ? 'flash_sucesso' : 'flash_erro', $n
            ? "{$n} contato(s) entraram na fila. Confira os textos e aprove."
            : 'Ninguém novo entrou: a fila já está no limite do dia ou não há clientes elegíveis.');
    }

    public function aprovar(Request $request)
    {
        $this->exigir();
        $ids = array_map('intval', (array) $request->input('ids', []));
        if (!$ids) {
            return back()->with('flash_erro', 'Marque os contatos que quer aprovar.');
        }
        $n = DB::table('crm_envios')->where('empresa_id', $request->empresa_id)->whereIn('id', $ids)->where('status', 'aguardando_aprovacao')
            ->update(['status' => 'pendente', 'aprovado_por' => $this->usuarioId(), 'aprovado_em' => now(), 'updated_at' => now()]);
        return back()->with('flash_sucesso', "{$n} contato(s) aprovado(s)." . (Crm::config((int) $request->empresa_id)['envio_ligado'] ? '' : ' O envio automático ainda não está ligado (fase 4).'));
    }

    public function texto(Request $request, $id)
    {
        $this->exigir();
        $request->validate(['texto' => 'required|string|min:5|max:1000']);
        $n = DB::table('crm_envios')->where('empresa_id', $request->empresa_id)->where('id', (int) $id)
            ->whereIn('status', ['aguardando_aprovacao', 'pendente'])->update(['texto' => trim($request->texto), 'updated_at' => now()]);
        return back()->with($n ? 'flash_sucesso' : 'flash_erro', $n ? 'Texto alterado.' : 'Este envio não pode mais ser alterado.');
    }

    public function cancelar(Request $request, $id)
    {
        $this->exigir();
        $e = DB::table('crm_envios')->where('empresa_id', $request->empresa_id)->where('id', (int) $id)->first();
        if (!$e || !in_array($e->status, ['aguardando_aprovacao', 'pendente'], true)) {
            return back()->with('flash_erro', 'Este envio não pode mais ser cancelado.');
        }
        Fila::encerrar((int) $e->ciclo_id, 'cancelado', 'retirado da fila manualmente');
        return back()->with('flash_sucesso', 'Contato retirado da fila. Ele não entra de novo por esta mesma compra.');
    }

    public function bloquear(Request $request)
    {
        $this->exigir();
        $tel = Crm::telefone($request->telefone);
        if (!$tel) {
            return back()->with('flash_erro', 'Telefone inválido: informe com DDD.');
        }
        Fila::bloquear((int) $request->empresa_id, $tel, 'erp', $request->observacao, $this->usuarioId());
        return back()->with('flash_sucesso', Crm::formatarTelefone($tel) . ' não recebe mais mensagens do CRM.');
    }

    public function desbloquear(Request $request, $id)
    {
        $this->exigir();
        DB::table('crm_bloqueios')->where('empresa_id', $request->empresa_id)->where('id', (int) $id)->delete();
        return back()->with('flash_sucesso', 'Telefone liberado para o CRM.');
    }

    public function historico(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        $camps = Crm::campanhas($emp);
        [$ini, $fim] = $this->periodo($request);
        $q = DB::table('crm_ciclos')->where('empresa_id', $emp)->whereBetween('created_at', [$ini, $fim]);
        if ($request->filled('campanha')) {
            $q->where('campanha_id', (int) $request->campanha);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('busca')) {
            $b = trim($request->busca);
            $d = preg_replace('/\D+/', '', $b);
            $q->where(fn ($w) => $w->where('nome', 'like', "%{$b}%")->when(strlen($d) >= 4, fn ($x) => $x->orWhere('telefone', 'like', "%{$d}%")));
        }
        $ciclos = $q->orderByDesc('id')->paginate(40)->withQueryString();
        $envios = DB::table('crm_envios')->whereIn('ciclo_id', $ciclos->pluck('id'))->orderBy('tentativa')->get()->groupBy('ciclo_id');
        $bloqueios = DB::table('crm_bloqueios')->where('empresa_id', $emp)->orderByDesc('id')->limit(200)->get();
        return view('crm.historico', compact('camps', 'ciclos', 'envios', 'bloqueios', 'ini', 'fim'));
    }

    public function config(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        return view('crm.config', ['cfg' => Crm::config($emp), 'camps' => Crm::campanhas($emp)]);
    }

    public function salvarConfig(Request $request)
    {
        $this->exigir();
        $emp = (int) $request->empresa_id;
        $request->validate([
            'limite_diario' => 'required|integer|min:1|max:200',
            'hora_inicio' => 'required|integer|min:0|max:23',
            'hora_fim' => 'required|integer|min:1|max:24|gt:hora_inicio',
            'intervalo_min_seg' => 'required|integer|min:30|max:3600',
            'intervalo_max_seg' => 'required|integer|gte:intervalo_min_seg|max:7200',
            'segunda_tentativa_dias' => 'required|integer|min:1|max:60',
            'tentativas' => 'required|integer|min:1|max:2',
            'intervalo_campanhas_dias' => 'required|integer|min:1|max:180',
            'janela_atribuicao_dias' => 'required|integer|min:1|max:60',
            'camp' => 'array',
        ]);
        Crm::salvarConfig($emp, [
            'geracao_automatica' => $request->boolean('geracao_automatica'),
            'aprovar_antes' => $request->boolean('aprovar_antes'),
            'limite_diario' => (int) $request->limite_diario,
            'hora_inicio' => (int) $request->hora_inicio,
            'hora_fim' => (int) $request->hora_fim,
            'dias_semana' => array_values(array_intersect(array_map('intval', (array) $request->dias_semana), [1, 2, 3, 4, 5, 6, 7])),
            'intervalo_min_seg' => (int) $request->intervalo_min_seg,
            'intervalo_max_seg' => (int) $request->intervalo_max_seg,
            'segunda_tentativa_dias' => (int) $request->segunda_tentativa_dias,
            'tentativas' => (int) $request->tentativas,
            'intervalo_campanhas_dias' => (int) $request->intervalo_campanhas_dias,
            'janela_atribuicao_dias' => (int) $request->janela_atribuicao_dias,
        ]);
        foreach ((array) $request->input('camp', []) as $id => $c) {
            $min = max(0, (int) ($c['dias_min'] ?? 0));
            $max = ($c['dias_max'] ?? '') === '' ? null : max($min, (int) $c['dias_max']);
            DB::table('crm_campanhas')->where('empresa_id', $emp)->where('id', (int) $id)->update([
                'ativa' => !empty($c['ativa']) ? 1 : 0,
                'dias_min' => $min,
                'dias_max' => $max,
                'texto_1' => mb_substr(trim((string) ($c['texto_1'] ?? '')), 0, 4000),
                'texto_2' => mb_substr(trim((string) ($c['texto_2'] ?? '')), 0, 4000),
                'updated_at' => now(),
            ]);
        }
        return back()->with('flash_sucesso', 'Configurações do CRM salvas.');
    }
}
