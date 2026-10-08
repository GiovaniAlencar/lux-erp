<?php

namespace App\Helpers;

/**
 * Gera o "PIX copia e cola" (BR Code estático, padrão EMV do Banco Central) com valor.
 * As chaves ficam no .env (ver config/lux.php).
 */
class PixCopiaCola
{
    /**
     * @param string $conta 'fiscal' | 'nao_fiscal'
     * @return string|null null se a chave da conta não estiver configurada ou valor <= 0
     */
    public static function paraConta(string $conta, float $valor, ?string $txid = null): ?string
    {
        $cfg = config('lux.pix.' . $conta);
        if (empty($cfg['chave']) || $valor <= 0) {
            return null;
        }
        return self::gerar($cfg['chave'], $cfg['nome'] ?? '', $cfg['cidade'] ?? '', $valor, $txid);
    }

    public static function configurado(string $conta): bool
    {
        return !empty(config('lux.pix.' . $conta . '.chave'));
    }

    public static function gerar(string $chave, string $nome, string $cidade, float $valor, ?string $txid = null): string
    {
        $chave = self::normalizarChave($chave);
        $nome = self::texto($nome, 25) ?: 'RECEBEDOR';
        $cidade = self::texto($cidade, 15) ?: 'SAO PAULO';
        $txid = preg_replace('/[^A-Za-z0-9]/', '', (string) $txid);
        $txid = $txid !== '' ? substr($txid, 0, 25) : '***';

        $conta = self::campo('00', 'br.gov.bcb.pix') . self::campo('01', $chave);

        $payload = self::campo('00', '01')
            . self::campo('26', $conta)
            . self::campo('52', '0000')
            . self::campo('53', '986')
            . self::campo('54', number_format($valor, 2, '.', ''))
            . self::campo('58', 'BR')
            . self::campo('59', $nome)
            . self::campo('60', $cidade)
            . self::campo('62', self::campo('05', $txid))
            . '6304';

        return $payload . self::crc16($payload);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
    }

    /** CPF/CNPJ com pontuação vira só dígitos; o resto (e-mail, +55..., aleatória) fica como está. */
    private static function normalizarChave(string $chave): string
    {
        $chave = trim($chave);
        if (preg_match('/^[\d.\-\/\s]+$/', $chave)) {
            return preg_replace('/\D/', '', $chave);
        }
        if (strpos($chave, '@') !== false) {
            return strtolower($chave);
        }
        return preg_replace('/\s+/', '', $chave);
    }

    private static function texto(string $s, int $max): string
    {
        $s = trim($s);
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($conv !== false) {
                $s = $conv;
            }
        }
        $s = strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $s));
        return substr(trim(preg_replace('/\s+/', ' ', $s)), 0, $max);
    }

    /** CRC16-CCITT (0x1021, inicial 0xFFFF) exigido pelo BR Code. */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
