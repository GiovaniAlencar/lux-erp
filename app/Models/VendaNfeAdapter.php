<?php

namespace App\Models;

/**
 * "Venda" só em memória, usada para passar uma NotaFiscal ao emissor (NFService).
 * Nunca grava no banco: alguns métodos do emissor chamam $venda->save() (ex.: carta de correção).
 */
class VendaNfeAdapter extends Venda
{
    public function save(array $options = [])
    {
        return true;
    }

    public function update(array $attributes = [], array $options = [])
    {
        $this->forceFill($attributes);
        return true;
    }

    public function delete()
    {
        return false;
    }
}
