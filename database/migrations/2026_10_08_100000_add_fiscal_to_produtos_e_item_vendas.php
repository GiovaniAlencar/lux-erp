<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Divisão fiscal / não fiscal da venda.
 *
 * - produtos.fiscal: produto cuja NF-e é emitida em outro sistema (valor vai pra conta fiscal).
 * - item_vendas.fiscal: cópia do produtos.fiscal no momento da venda, para que mudar o
 *   cadastro depois não altere vendas antigas / fechamentos já feitos.
 *
 * Só adiciona colunas com default 0: vendas e produtos existentes ficam como "não fiscal".
 */
class AddFiscalToProdutosEItemVendas extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('produtos', 'fiscal')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->boolean('fiscal')->default(false)->after('valor_venda');
            });
        }

        if (!Schema::hasColumn('item_vendas', 'fiscal')) {
            Schema::table('item_vendas', function (Blueprint $table) {
                $table->boolean('fiscal')->default(false)->after('valor_custo');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('item_vendas', 'fiscal')) {
            Schema::table('item_vendas', function (Blueprint $table) {
                $table->dropColumn('fiscal');
            });
        }

        if (Schema::hasColumn('produtos', 'fiscal')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('fiscal');
            });
        }
    }
}
