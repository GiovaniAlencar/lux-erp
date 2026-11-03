<?php

namespace App\Http\Controllers;

use App\Services\Financeiro;
use App\Services\FechamentoMes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Financeiro › Fechamento do mês. Só administrador. */
class FechamentoController extends Controller
{
    private function exigeAdm(): void
    {
        $u = session('user_logged');
        if (!($u['adm'] ?? false) && empty($u['super'])) {
            abort(403, 'Somente administradores veem o fechamento.');
        }
        if (!FechamentoMes::ativo()) {
            abort(redirect('/')->with('flash_erro', 'Rode o SQL 34 (fechamento do mês) para usar esta tela.'));
        }
    }

    private function mes(Request $request): string
    {
        $m = (string) $request->get('mes', now()->subMonthNoOverflow()->format('Y-m'));
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m');
    }

    private function registro(int $empresaId, string $mes): ?object
    {
        return DB::table('fechamentos_mes')->where('empresa_id', $empresaId)->where('mes', $mes)->first();
    }

    /** Entradas do mês; sem nada salvo, copia os sócios do último fechamento. */
    private function entradas(int $empresaId, string $mes, ?object $reg): array
    {
        if ($reg && $reg->entradas) {
            return json_decode($reg->entradas, true) ?: [];
        }
        $ultimo = DB::table('fechamentos_mes')->where('empresa_id', $empresaId)->where('mes', '<', $mes)->orderByDesc('mes')->value('entradas');
        $socios = $ultimo ? array_map(fn ($s) => ['nome' => $s['nome'], 'pct' => $s['pct'], 'retiradas' => []], (json_decode($ultimo, true)['socios'] ?? [])) : [];
        if (!$socios) {
            $socios = [['nome' => 'Giovani', 'pct' => 35, 'retiradas' => []], ['nome' => 'Ian', 'pct' => 65, 'retiradas' => []]];
        }
        return ['socios' => $socios, 'reinvestimento' => 0, 'extras' => []];
    }

    public function index(Request $request)
    {
        $this->exigeAdm();
        $mes = $this->mes($request);
        $empresaId = (int) $request->empresa_id;
        $reg = $this->registro($empresaId, $mes);
        $entradas = $this->entradas($empresaId, $mes, $reg);
        $fechado = $reg && $reg->status === 'fechado' && $reg->snapshot;
        $r = $fechado ? json_decode($reg->snapshot, true) : FechamentoMes::calcular($empresaId, $mes, $entradas);
        $texto = FechamentoMes::texto($r);
        $historico = DB::table('fechamentos_mes')->where('empresa_id', $empresaId)->orderByDesc('mes')->limit(12)->get(['mes', 'status', 'fechado_em']);
        return view('financeiro.fechamento', compact('mes', 'r', 'entradas', 'fechado', 'reg', 'texto', 'historico'));
    }

    public function salvar(Request $request)
    {
        $this->exigeAdm();
        $mes = $this->mes($request);
        $empresaId = (int) $request->empresa_id;
        $reg = $this->registro($empresaId, $mes);
        if ($reg && $reg->status === 'fechado') {
            return back()->with('flash_erro', 'Este mês está fechado. Reabra para alterar.');
        }
        $socios = [];
        foreach ((array) $request->input('socios', []) as $s) {
            $nome = trim((string) ($s['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $ret = [];
            foreach ((array) ($s['retiradas'] ?? []) as $x) {
                $v = Financeiro::valorBr($x['valor'] ?? '');
                if ($v != 0) {
                    $ret[] = ['descricao' => mb_substr(trim((string) ($x['descricao'] ?? '')), 0, 80), 'valor' => $v];
                }
            }
            $socios[] = ['nome' => mb_substr($nome, 0, 40), 'pct' => Financeiro::valorBr($s['pct'] ?? '0'), 'retiradas' => $ret];
        }
        $extras = [];
        foreach ((array) $request->input('extras', []) as $x) {
            $v = Financeiro::valorBr($x['valor'] ?? '');
            $d = trim((string) ($x['descricao'] ?? ''));
            if ($v != 0 && $d !== '') {
                $extras[] = ['tipo' => ($x['tipo'] ?? '') === 'pagar' ? 'pagar' : 'receber', 'descricao' => mb_substr($d, 0, 80), 'valor' => $v];
            }
        }
        $entradas = ['socios' => $socios, 'reinvestimento' => Financeiro::valorBr($request->input('reinvestimento', '0')), 'extras' => $extras];
        $dados = ['entradas' => json_encode($entradas, JSON_UNESCAPED_UNICODE), 'usuario_id' => (int) (session('user_logged')['id'] ?? 0) ?: null, 'updated_at' => now()];

        if ($request->input('acao') === 'fechar') {
            $dados['snapshot'] = json_encode(FechamentoMes::calcular($empresaId, $mes, $entradas), JSON_UNESCAPED_UNICODE);
            $dados['status'] = 'fechado';
            $dados['fechado_em'] = now();
        }
        DB::table('fechamentos_mes')->updateOrInsert(['empresa_id' => $empresaId, 'mes' => $mes], $dados + ['created_at' => $reg->created_at ?? now()]);

        return redirect()->route('fin.fechamento', ['mes' => $mes])
            ->with('flash_sucesso', $request->input('acao') === 'fechar' ? 'Mês fechado: os números ficaram guardados como estavam agora.' : 'Fechamento salvo.');
    }

    public function reabrir(Request $request)
    {
        $this->exigeAdm();
        $mes = $this->mes($request);
        DB::table('fechamentos_mes')->where('empresa_id', $request->empresa_id)->where('mes', $mes)
            ->update(['status' => 'aberto', 'snapshot' => null, 'fechado_em' => null, 'updated_at' => now()]);
        return redirect()->route('fin.fechamento', ['mes' => $mes])->with('flash_sucesso', 'Mês reaberto: os números voltam a ser calculados ao vivo.');
    }
}
