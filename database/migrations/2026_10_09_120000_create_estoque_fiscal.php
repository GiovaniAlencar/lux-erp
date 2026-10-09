<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo de estoque FISCAL (unidades com nota de entrada) por produto.
 * - produtos.estoque_fiscal: saldo atual com nota
 * - item_vendas.qtd_fiscal: quantas unidades do item saíram como fiscal (conta fiscal / NF-e)
 * - estoque_fiscal_movimentos: histórico (entrada por XML, ajuste, venda, estorno)
 */
class CreateEstoqueFiscal extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('produtos', 'estoque_fiscal')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->decimal('estoque_fiscal', 14, 3)->default(0)->after('fiscal');
            });
        }
        if (!Schema::hasColumn('item_vendas', 'qtd_fiscal')) {
            Schema::table('item_vendas', function (Blueprint $table) {
                $table->decimal('qtd_fiscal', 14, 3)->default(0)->after('fiscal');
            });
            DB::table('item_vendas')->where('fiscal', 1)->update(['qtd_fiscal' => DB::raw('quantidade')]);
        }
        if (!Schema::hasTable('estoque_fiscal_movimentos')) {
            Schema::create('estoque_fiscal_movimentos', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('produto_id')->index();
                $table->string('tipo', 20); // entrada_xml | ajuste | venda | estorno
                $table->decimal('quantidade', 14, 3);
                $table->decimal('saldo_apos', 14, 3);
                $table->unsignedInteger('venda_id')->nullable();
                $table->string('chave', 44)->nullable()->index();
                $table->string('documento', 60)->nullable();
                $table->string('observacao', 255)->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('estoque_fiscal_movimentos');
        if (Schema::hasColumn('item_vendas', 'qtd_fiscal')) {
            Schema::table('item_vendas', fn (Blueprint $t) => $t->dropColumn('qtd_fiscal'));
        }
        if (Schema::hasColumn('produtos', 'estoque_fiscal')) {
            Schema::table('produtos', fn (Blueprint $t) => $t->dropColumn('estoque_fiscal'));
        }
    }
}
