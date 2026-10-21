<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Cliente mínimo da Evolution API (v2) para mandar arquivos e mensagens a grupos do WhatsApp.
 * Configuração em config/lux.php → 'evolution' (EVOLUTION_URL, EVOLUTION_KEY, EVOLUTION_INSTANCIA).
 */
class Evolution
{
    public static function configurada(): bool
    {
        $c = config('lux.evolution');
        return !empty($c['url']) && !empty($c['key']) && !empty($c['instancia']);
    }

    /** Envio de comprovantes ligado? (Evolution configurada + grupo escolhido) */
    public static function grupoComprovantes(): ?string
    {
        $g = trim((string) config('lux.evolution.grupo_comprovantes'));
        return self::configurada() && str_ends_with($g, '@g.us') ? $g : null;
    }

    private static function http()
    {
        return Http::withHeaders(['apikey' => (string) config('lux.evolution.key')])
            ->acceptJson()->timeout(60)->connectTimeout(10);
    }

    /** @param string|null $instancia null = instância dos comprovantes (EVOLUTION_INSTANCIA) */
    private static function url(string $caminho, ?string $instancia = null): string
    {
        $inst = $instancia ?: (string) config('lux.evolution.instancia');
        return config('lux.evolution.url') . $caminho . '/' . rawurlencode($inst);
    }

    /** Instância que fala com os clientes (número do atendimento). null = avisos por WhatsApp desligados. */
    public static function instanciaClientes(): ?string
    {
        $c = config('lux.evolution');
        $i = trim((string) ($c['instancia_clientes'] ?? ''));
        return (!empty($c['url']) && !empty($c['key']) && $i !== '') ? $i : null;
    }

    /** Lista os grupos do número conectado: [['id' => '...@g.us', 'nome' => '...'], ...] */
    public static function grupos(): array
    {
        $r = self::http()->get(self::url('/group/fetchAllGroups'), ['getParticipants' => 'false']);
        if (!$r->successful()) {
            throw new \RuntimeException('Evolution respondeu ' . $r->status() . ': ' . mb_substr($r->body(), 0, 300));
        }
        return collect($r->json() ?: [])->map(fn ($g) => ['id' => $g['id'] ?? '', 'nome' => $g['subject'] ?? ''])
            ->filter(fn ($g) => str_ends_with($g['id'], '@g.us'))->values()->all();
    }

    /** Manda um arquivo do disco "local" (privado) com legenda. Lança exceção se falhar. */
    public static function enviarArquivo(string $destino, string $caminhoLocal, string $legenda, ?string $nomeArquivo = null): void
    {
        $disco = Storage::disk('local');
        if (!$disco->exists($caminhoLocal)) {
            throw new \RuntimeException('Arquivo do comprovante não encontrado.');
        }
        $ext = strtolower(pathinfo($caminhoLocal, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
        $ehImagem = str_starts_with($mime, 'image/');

        $r = self::http()->post(self::url('/message/sendMedia'), [
            'number' => $destino,
            'mediatype' => $ehImagem ? 'image' : 'document',
            'mimetype' => $mime,
            'caption' => $legenda,
            'media' => base64_encode($disco->get($caminhoLocal)), // o arquivo vai junto: nada fica público
            'fileName' => $nomeArquivo ?: ('comprovante.' . $ext),
        ]);
        if (!$r->successful()) {
            throw new \RuntimeException('Evolution respondeu ' . $r->status() . ': ' . mb_substr($r->body(), 0, 300));
        }
    }

    /** Manda um documento gerado na hora (ex.: DANFE em PDF). Lança exceção se falhar. */
    public static function enviarDocumento(string $destino, string $conteudo, string $mime, string $nomeArquivo, string $legenda = '', ?string $instancia = null): void
    {
        $r = self::http()->post(self::url('/message/sendMedia', $instancia), [
            'number' => $destino,
            'mediatype' => 'document',
            'mimetype' => $mime,
            'caption' => $legenda,
            'media' => base64_encode($conteudo),
            'fileName' => $nomeArquivo,
        ]);
        if (!$r->successful()) {
            throw new \RuntimeException('Evolution respondeu ' . $r->status() . ': ' . mb_substr($r->body(), 0, 300));
        }
    }

    /** Mensagem só de texto. */
    public static function enviarTexto(string $destino, string $texto, ?string $instancia = null): void
    {
        $r = self::http()->post(self::url('/message/sendText', $instancia), ['number' => $destino, 'text' => $texto]);
        if (!$r->successful()) {
            throw new \RuntimeException('Evolution respondeu ' . $r->status() . ': ' . mb_substr($r->body(), 0, 300));
        }
    }
}
