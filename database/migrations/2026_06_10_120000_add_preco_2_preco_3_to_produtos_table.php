<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreco2Preco3ToProdutosTable extends Migration
{
    public function up()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('preco_2', 10, 2)->nullable()->after('valor_venda');
            $table->decimal('preco_3', 10, 2)->nullable()->after('preco_2');
        });
    }

    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['preco_2', 'preco_3']);
        });
    }
}
