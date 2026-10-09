<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NF-e emitida a partir da parte FISCAL de uma venda (só itens com estoque fiscal).
 * A nota é um rascunho editável até ser transmitida.
 */
class CreateNotasFiscaisTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('notas_fiscais')) {
            Schema::create('notas_fiscais', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('venda_id')->nullable()->index();
                $table->unsignedInteger('cliente_id');
                $table->unsignedInteger('natureza_id')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                // rascunho | autorizada | rejeitada | cancelada
                $table->string('status', 20)->default('rascunho')->index();
                $table->unsignedTinyInteger('ambiente')->default(2);
                $table->unsignedSmallInteger('serie')->nullable();
                $table->unsignedInteger('numero')->nullable();
                $table->string('chave', 44)->nullable()->index();
                $table->string('protocolo', 20)->nullable();
                $table->dateTime('data_emissao')->nullable();
                $table->dateTime('autorizada_em')->nullable();
                $table->decimal('valor_produtos', 16, 2)->default(0);
                $table->decimal('valor_frete', 16, 2)->default(0);
                $table->decimal('valor_desconto', 16, 2)->default(0);
                $table->decimal('valor_outros', 16, 2)->default(0);
                $table->decimal('valor_total', 16, 2)->default(0);
                $table->string('tipo_pagamento', 2)->default('17');
                $table->text('info_complementar')->nullable();
                $table->text('ultimo_retorno')->nullable();
                $table->dateTime('cancelada_em')->nullable();
                $table->string('motivo_cancelamento', 255)->nullable();
                $table->unsignedInteger('sequencia_cce')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nota_fiscal_itens')) {
            Schema::create('nota_fiscal_itens', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('nota_fiscal_id')->index();
                $table->unsignedInteger('produto_id');
                $table->unsignedInteger('item_venda_id')->nullable();
                $table->string('descricao', 150);
                $table->decimal('quantidade', 14, 4);
                $table->decimal('valor_unitario', 16, 4);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('nota_fiscal_itens');
        Schema::dropIfExists('notas_fiscais');
    }
}
