<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFornecedorCompraToImportacaoMassaProdutos extends Migration
{
    public function up()
    {
        Schema::table('importacao_massa_produtos', function (Blueprint $table) {
            $table->unsignedInteger('fornecedor_id')->nullable()->after('usuario_id');
            $table->unsignedInteger('compra_id')->nullable()->after('fornecedor_id');
        });
    }

    public function down()
    {
        Schema::table('importacao_massa_produtos', function (Blueprint $table) {
            $table->dropColumn(['fornecedor_id', 'compra_id']);
        });
    }
}
