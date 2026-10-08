<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportacaoMassaProdutosTables extends Migration
{
    public function up()
    {
        Schema::create('importacao_massa_produtos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('usuario_id');
            $table->string('arquivo_nome', 255)->nullable();
            $table->unsignedInteger('qtd_itens')->default(0);
            $table->unsignedInteger('qtd_itens_validos')->default(0);
            $table->unsignedInteger('qtd_itens_erro')->default(0);
            $table->decimal('qtd_unidades', 14, 3)->default(0);
            $table->decimal('valor_total_compra', 14, 2)->default(0);
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('importacao_massa_produto_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacao_id');
            $table->unsignedInteger('produto_id')->nullable();
            $table->unsignedInteger('codigo_informado');
            $table->string('produto_nome', 255)->nullable();
            $table->decimal('estoque_anterior', 14, 3)->default(0);
            $table->decimal('quantidade', 14, 3)->default(0);
            $table->decimal('estoque_final', 14, 3)->default(0);
            $table->decimal('custo_anterior', 14, 2)->nullable();
            $table->decimal('custo_novo', 14, 2)->nullable();
            $table->decimal('preco_1_anterior', 14, 2)->nullable();
            $table->decimal('preco_1_novo', 14, 2)->nullable();
            $table->decimal('preco_2_anterior', 14, 2)->nullable();
            $table->decimal('preco_2_novo', 14, 2)->nullable();
            $table->decimal('preco_3_anterior', 14, 2)->nullable();
            $table->decimal('preco_3_novo', 14, 2)->nullable();
            $table->decimal('valor_linha', 14, 2)->default(0);
            $table->boolean('valido')->default(true);
            $table->string('erro', 500)->nullable();
            $table->timestamps();

            $table->foreign('importacao_id')
                ->references('id')
                ->on('importacao_massa_produtos')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('importacao_massa_produto_itens');
        Schema::dropIfExists('importacao_massa_produtos');
    }
}
