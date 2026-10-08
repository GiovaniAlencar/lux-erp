<?php

namespace App\Services;

use App\Models\Estoque;
use App\Models\Produto;

class ImportacaoMassaProdutoService
{
    public const CABECALHO = ['codigo', 'quantidade', 'custo', 'preco_1', 'preco_2', 'preco_3'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseCsvFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo.');
        }

        $rows = [];
        $lineNum = 0;
        $headerOk = false;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                $header = array_map(function ($c) {
                    $c = trim((string) $c);
                    $c = preg_replace('/^\xEF\xBB\xBF/', '', $c);

                    return strtolower($c);
                }, $data);
                if ($header !== self::CABECALHO) {
                    fclose($handle);
                    throw new \RuntimeException(
                        'Cabeçalho inválido. Use: ' . implode(',', self::CABECALHO)
                    );
                }
                $headerOk = true;
                continue;
            }

            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $rows[] = [
                'linha' => $lineNum,
                'codigo' => trim((string) ($data[0] ?? '')),
                'quantidade' => trim((string) ($data[1] ?? '')),
                'custo' => trim((string) ($data[2] ?? '')),
                'preco_1' => trim((string) ($data[3] ?? '')),
                'preco_2' => trim((string) ($data[4] ?? '')),
                'preco_3' => trim((string) ($data[5] ?? '')),
            ];
        }

        fclose($handle);

        if (!$headerOk) {
            throw new \RuntimeException('Arquivo CSV vazio ou sem cabeçalho.');
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return array{linhas: array<int, array<string, mixed>>, resumo: array<string, mixed>}
     */
    public function montarPreview(array $rawRows, int $empresaId): array
    {
        $linhas = [];
        $totalUnidades = 0.0;
        $valorTotal = 0.0;
        $validos = 0;
        $erros = 0;

        foreach ($rawRows as $raw) {
            $linha = $this->validarLinha($raw, $empresaId);
            $linhas[] = $linha;

            if ($linha['valido']) {
                $validos++;
                $totalUnidades += (float) $linha['quantidade'];
                $valorTotal += (float) $linha['valor_linha'];
            } else {
                $erros++;
            }
        }

        return [
            'linhas' => $linhas,
            'resumo' => [
                'itens_processados' => count($linhas),
                'total_unidades' => $totalUnidades,
                'valor_total_compra' => round($valorTotal, 2),
                'produtos_validos' => $validos,
                'produtos_erro' => $erros,
                'tem_erros' => $erros > 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function validarLinha(array $raw, int $empresaId): array
    {
        $codigoStr = (string) ($raw['codigo'] ?? '');
        $erros = [];

        if ($codigoStr === '' || !ctype_digit($codigoStr)) {
            $erros[] = 'Código inválido';
        }

        $qtd = $this->parseNumero($raw['quantidade'] ?? '', 'quantidade', $erros);
        $custo = $this->parseNumero($raw['custo'] ?? '', 'custo', $erros);
        $p1 = $this->parseNumero($raw['preco_1'] ?? '', 'preco_1', $erros);
        $p2 = $this->parseNumero($raw['preco_2'] ?? '', 'preco_2', $erros);
        $p3 = $this->parseNumero($raw['preco_3'] ?? '', 'preco_3', $erros);

        $codigo = (int) $codigoStr;
        $produto = null;
        if ($codigo > 0 && empty(array_filter($erros, fn ($e) => str_contains($e, 'Código inválido')))) {
            $produto = Produto::where('empresa_id', $empresaId)
                ->where('id', $codigo)
                ->first();
            if (!$produto) {
                $erros[] = 'Código não encontrado: ' . $codigo;
            }
        }

        $estoqueAtual = 0.0;
        if ($produto) {
            $estoqueRow = Estoque::where('produto_id', $produto->id)
                ->whereNull('filial_id')
                ->first();
            $estoqueAtual = $estoqueRow ? (float) $estoqueRow->quantidade : 0.0;
        }

        $valido = empty($erros) && $produto !== null;
        $qtdF = $qtd ?? 0.0;
        $custoF = $custo ?? 0.0;

        return [
            'linha_arquivo' => (int) ($raw['linha'] ?? 0),
            'codigo' => $codigo,
            'produto_id' => $produto?->id,
            'nome' => $produto?->nome,
            'valido' => $valido,
            'erro' => implode(' | ', $erros),
            'estoque_atual' => $estoqueAtual,
            'quantidade' => $qtdF,
            'estoque_final' => $valido ? $estoqueAtual + $qtdF : null,
            'custo_atual' => $produto ? (float) $produto->valor_compra : null,
            'custo_novo' => $custoF,
            'preco_1_atual' => $produto ? (float) $produto->valor_venda : null,
            'preco_1_novo' => $p1 ?? 0.0,
            'preco_2_atual' => $produto && $produto->preco_2 !== null ? (float) $produto->preco_2 : null,
            'preco_2_novo' => $p2 ?? 0.0,
            'preco_3_atual' => $produto && $produto->preco_3 !== null ? (float) $produto->preco_3 : null,
            'preco_3_novo' => $p3 ?? 0.0,
            'valor_linha' => $valido ? round($qtdF * $custoF, 2) : 0.0,
        ];
    }

    private function parseNumero(string $valor, string $campo, array &$erros): ?float
    {
        $valor = trim($valor);
        if ($valor === '') {
            $erros[] = ucfirst(str_replace('_', ' ', $campo)) . ' não informado';

            return null;
        }

        $n = (float) __convert_value_bd($valor);
        if ($campo !== 'quantidade' && $n < 0) {
            $erros[] = ucfirst(str_replace('_', ' ', $campo)) . ' não pode ser negativo';
        }
        if ($campo === 'quantidade' && $n < 0) {
            $erros[] = 'Quantidade não pode ser negativa';
        }

        return round($n, $campo === 'quantidade' ? 3 : 2);
    }
}
