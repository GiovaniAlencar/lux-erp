<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Converte endereço em latitude/longitude pelo OpenStreetMap (Nominatim), de graça.
 * Regras de uso do Nominatim: no máximo 1 consulta por segundo e identificar o sistema
 * (User-Agent com contato). Cada endereço fica guardado em geo_enderecos (SQL 32) e
 * nunca é consultado de novo.
 */
class Geocodificador
{
    public static function ativo(): bool
    {
        return (bool) config('lux.geo.ativo', true) && Schema::hasTable('geo_enderecos');
    }

    public static function normalizar(string $endereco): string
    {
        $e = mb_strtolower(trim(preg_replace('/\s+/', ' ', $endereco)));
        return mb_substr($e, 0, 400);
    }

    /** Só o cache (sem consultar a internet). null = ainda não consultado. */
    public static function doCache(string $endereco): ?array
    {
        $r = DB::table('geo_enderecos')->where('chave', sha1(self::normalizar($endereco)))->first();
        if (!$r) {
            return null;
        }
        return ['lat' => $r->lat !== null ? (float) $r->lat : null, 'lng' => $r->lng !== null ? (float) $r->lng : null];
    }

    /**
     * Consulta (com cache). Tenta o endereço completo e, se não achar, só rua + cidade.
     * Sem internet / serviço fora: devolve erro=true e NÃO guarda nada (tenta de novo depois).
     * @return array{lat: float|null, lng: float|null, erro?: bool}
     */
    public static function buscar(string $completo, ?string $semNumero = null): array
    {
        $cache = self::doCache($completo);
        if ($cache !== null) {
            return $cache;
        }
        $achou = self::consultar($completo);
        if ($achou === false) {
            return ['lat' => null, 'lng' => null, 'erro' => true];
        }
        if (!$achou && $semNumero) {
            usleep(1100000); // respeita 1 consulta por segundo
            $achou = self::consultar($semNumero);
            if ($achou === false) {
                return ['lat' => null, 'lng' => null, 'erro' => true];
            }
        }
        DB::table('geo_enderecos')->insertOrIgnore([
            'chave' => sha1(self::normalizar($completo)),
            'endereco' => self::normalizar($completo),
            'lat' => $achou['lat'] ?? null,
            'lng' => $achou['lng'] ?? null,
            'created_at' => now(),
        ]);
        return ['lat' => $achou['lat'] ?? null, 'lng' => $achou['lng'] ?? null];
    }

    /** @return array|null|false  array = achou · null = não existe · false = erro de rede/serviço */
    private static function consultar(string $q): array|null|false
    {
        try {
            $r = Http::withHeaders([
                'User-Agent' => 'LuxERP/1.0 (' . config('lux.geo.contato', 'contato@luxatacado.com.br') . ')',
                'Accept-Language' => 'pt-BR',
            ])->timeout(10)->get(rtrim((string) config('lux.geo.url', 'https://nominatim.openstreetmap.org'), '/') . '/search', [
                'q' => $q,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'br',
            ]);
            if (!$r->successful()) {
                \Log::warning('Geocodificador: HTTP ' . $r->status());
                return false;
            }
            $j = $r->json();
            if (!is_array($j) || empty($j[0]['lat'])) {
                return null;
            }
            return ['lat' => (float) $j[0]['lat'], 'lng' => (float) $j[0]['lon']];
        } catch (\Throwable $e) {
            \Log::warning('Geocodificador: ' . $e->getMessage());
            return false;
        }
    }
}
