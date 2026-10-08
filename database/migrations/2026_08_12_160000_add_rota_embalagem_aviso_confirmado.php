<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rota_entrega_itens', function (Blueprint $table) {
            if (!Schema::hasColumn('rota_entrega_itens', 'embalagem_tipo')) {
                $table->string('embalagem_tipo', 20)->nullable()->after('complemento');
            }
            if (!Schema::hasColumn('rota_entrega_itens', 'embalagem_qtd')) {
                $table->unsignedSmallInteger('embalagem_qtd')->nullable()->after('embalagem_tipo');
            }
            if (!Schema::hasColumn('rota_entrega_itens', 'aviso_entrega')) {
                $table->string('aviso_entrega', 255)->nullable()->after('observacao_prioridade');
            }
            if (!Schema::hasColumn('rota_entrega_itens', 'confirmado_saida')) {
                $table->boolean('confirmado_saida')->default(false)->after('aviso_entrega');
            }
        });

        Schema::table('vendas', function (Blueprint $table) {
            if (!Schema::hasColumn('vendas', 'modo_preco_arabes')) {
                $table->string('modo_preco_arabes', 20)->default('auto')->after('status_pagamento');
            }
            if (!Schema::hasColumn('vendas', 'modo_preco_miniaturas')) {
                $table->string('modo_preco_miniaturas', 20)->default('auto')->after('modo_preco_arabes');
            }
            if (!Schema::hasColumn('vendas', 'aviso_entrega')) {
                $table->string('aviso_entrega', 255)->nullable()->after('observacao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rota_entrega_itens', function (Blueprint $table) {
            $cols = ['embalagem_tipo', 'embalagem_qtd', 'aviso_entrega', 'confirmado_saida'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('rota_entrega_itens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('vendas', function (Blueprint $table) {
            $cols = ['modo_preco_arabes', 'modo_preco_miniaturas', 'aviso_entrega'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('vendas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
