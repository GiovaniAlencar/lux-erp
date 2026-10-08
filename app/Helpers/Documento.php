<?php

namespace App\Helpers;

/** Validação e máscara de CPF/CNPJ (dígitos verificadores de verdade). */
class Documento
{
    public static function digitos(?string $doc): string
    {
        return preg_replace('/\D/', '', (string) $doc);
    }

    public static function valido(?string $doc): bool
    {
        $d = self::digitos($doc);
        if (strlen($d) === 11) {
            return self::cpfValido($d);
        }
        if (strlen($d) === 14) {
            return self::cnpjValido($d);
        }
        return false;
    }

    public static function formatar(?string $doc): string
    {
        $d = self::digitos($doc);
        if (strlen($d) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        }
        if (strlen($d) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        }
        return (string) $doc;
    }

    private static function cpfValido(string $c): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += (int) $c[$i] * (($t + 1) - $i);
            }
            $dv = ((10 * $s) % 11) % 10;
            if ((int) $c[$t] !== $dv) {
                return false;
            }
        }
        return true;
    }

    private static function cnpjValido(string $c): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }
        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
        foreach ([12, 13] as $k => $len) {
            $s = 0;
            for ($i = 0; $i < $len; $i++) {
                $s += (int) $c[$i] * $pesos[$k][$i];
            }
            $r = $s % 11;
            $dv = $r < 2 ? 0 : 11 - $r;
            if ((int) $c[$len] !== $dv) {
                return false;
            }
        }
        return true;
    }
}
