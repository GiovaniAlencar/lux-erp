<?php

namespace App\Http\Controllers;

use App\Mail\NotificacaoLux;
use App\Services\NotificacaoModelos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** Tela "Notificações do Site": o que foi enviado, o que falhou, reenviar e testar o e-mail. */
class NotificacaoController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('notificacoes')) {
            session()->flash('flash_erro', 'Rode o SQL 21 (tabela notificacoes) para usar esta tela.');
            return redirect('/');
        }
        $q = DB::table('notificacoes as n')
            ->leftJoin('clientes as c', 'c.id', '=', 'n.cliente_id')
            ->select('n.*', 'c.razao_social as cliente_nome');
        if ($request->filled('status')) {
            $q->where('n.status', $request->status);
        }
        if ($request->filled('evento')) {
            $q->where('n.evento', $request->evento);
        }
        if ($request->filled('busca')) {
            $termo = trim($request->busca);
            $b = '%' . str_replace(['%', '_'], ['\%', '\_'], $termo) . '%';
            $digitos = preg_replace('/\D+/', '', $termo);
            $q->where(function ($w) use ($b, $digitos, $termo) {
                $w->where('n.email', 'like', $b)->orWhere('c.razao_social', 'like', $b);
                if (strlen($digitos) >= 4) {
                    $w->orWhere('n.telefone', 'like', '%' . $digitos . '%');
                }
                if (ctype_digit(ltrim($termo, '#'))) { // número do pedido
                    $num = ltrim($termo, '#');
                    $w->orWhere('n.origem', 'venda#' . $num)
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(n.dados, '$.pedido')) = ?", [$num]);
                }
            });
        }
        $itens = $q->orderByDesc('n.id')->paginate(50)->withQueryString();

        $resumo = DB::table('notificacoes')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');

        $eventos = DB::table('notificacoes')->distinct()->orderBy('evento')->pluck('evento');

        return view('notificacoes_site.index', compact('itens', 'resumo', 'eventos'));
    }

    public function reenviar($id)
    {
        $n = DB::table('notificacoes')->where('id', (int) $id)->first();
        if (!$n) {
            abort(404);
        }
        if (in_array($n->evento, NotificacaoModelos::SEM_REENVIO, true)) {
            session()->flash('flash_erro', 'Este tipo não pode ser reenviado (o link é de uso único). Peça para o cliente solicitar de novo no site.');
            return back();
        }
        DB::table('notificacoes')->where('id', $n->id)->update([
            'status' => 'pendente',
            'tentativas' => 0,
            'erro' => null,
            'enviar_apos' => now(),
        ]);
        session()->flash('flash_sucesso', 'Colocado na fila. Sai em até 1 minuto.');
        return back();
    }

    public function teste(Request $request)
    {
        $request->validate(['email' => 'required|email:rfc|max:150']);
        try {
            Mail::to($request->email)->send(new NotificacaoLux(NotificacaoModelos::montar('teste', [])));
            session()->flash('flash_sucesso', 'E-mail de teste enviado para ' . $request->email . '. Confira a caixa de entrada (e o spam).');
        } catch (\Throwable $e) {
            session()->flash('flash_erro', 'Falhou: ' . mb_substr($e->getMessage(), 0, 300));
        }
        return back();
    }

    /** Pré-visualização dos modelos (com dados de exemplo). */
    public function preview($evento)
    {
        $exemplo = [
            'nome' => 'MARIA DA SILVA', 'pedido' => 'SITE123456', 'total' => 1289.5, 'numero' => '1234',
            'expira_em' => now()->addDay()->format('Y-m-d H:i:s'),
            'produto' => 'Lattafa Asad Bourbon 100ml', 'produto_id' => 595,
            'token' => str_repeat('0', 64), 'validade_minutos' => 60,
        ];
        $m = NotificacaoModelos::montar($evento, $exemplo);
        if (!$m) {
            abort(404);
        }
        return view('emails.lux.layout', ['m' => $m]);
    }
}
