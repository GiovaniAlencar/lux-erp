<?php

namespace App\Console\Commands;

use App\Models\RotaEntregaItem;
use App\Services\Geocodificador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Preenche latitude/longitude das entregas das rotas abertas (rascunho / em rota),
 * para o app do motoboy ordenar por distância. Agendado a cada minuto; no máximo
 * ~40 endereços por execução (1 consulta por segundo, regra do OpenStreetMap).
 */
class GeocodificarRotas extends Command
{
    protected $signature = 'rotas:geocodificar {--limite=40}';
    protected $description = 'Converte os endereços das entregas em coordenadas (OpenStreetMap)';

    public function handle()
    {
        if (!Geocodificador::ativo() || !Schema::hasColumn('rota_entrega_itens', 'lat')) {
            return self::SUCCESS;
        }
        $itens = RotaEntregaItem::with(['rota:id,status', 'venda.cliente.cidade'])
            ->whereNull('geo_status')
            ->whereHas('rota', fn ($q) => $q->whereIn('status', ['rascunho', 'em_rota']))
            ->orderBy('id')->limit(max(1, min(200, (int) $this->option('limite'))))->get();

        $ok = 0;
        foreach ($itens as $item) {
            [$completo, $semNumero] = self::enderecos($item);
            if ($completo === '') {
                $item->geo_status = 'falhou';
                $item->save();
                continue;
            }
            $tinhaCache = Geocodificador::doCache($completo) !== null;
            $g = Geocodificador::buscar($completo, $semNumero);
            if (!empty($g['erro'])) {
                $this->warn('OpenStreetMap fora do ar ou sem internet; tenta de novo no próximo minuto.');
                break;
            }
            $item->lat = $g['lat'];
            $item->lng = $g['lng'];
            $item->geo_status = $g['lat'] !== null ? 'ok' : 'falhou';
            $item->save();
            $ok += $g['lat'] !== null ? 1 : 0;
            if (!$tinhaCache) {
                usleep(1100000);
            }
        }
        if ($itens->isNotEmpty()) {
            $this->info("{$ok} de {$itens->count()} endereço(s) localizados.");
        }
        return self::SUCCESS;
    }

    /** [endereço completo, endereço sem número] para consultar. */
    public static function enderecos(RotaEntregaItem $item): array
    {
        $cid = optional(optional(optional($item->venda)->cliente)->cidade);
        $cidade = $cid->nome ? $cid->nome . ' - ' . $cid->uf : (string) config('lux.geo.cidade_padrao', 'João Pessoa - PB');
        $rua = trim((string) $item->rua);
        if ($rua === '') {
            return ['', null];
        }
        $num = trim((string) $item->numero);
        $bairro = trim((string) $item->bairro);
        $completo = $rua . ($num !== '' && strtolower($num) !== 's/n' ? ', ' . $num : '') . ($bairro !== '' ? ', ' . $bairro : '') . ', ' . $cidade . ', Brasil';
        $semNumero = $rua . ', ' . $cidade . ', Brasil';
        return [$completo, $semNumero];
    }
}
