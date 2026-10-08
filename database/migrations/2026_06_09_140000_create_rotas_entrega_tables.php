<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotas_entrega', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id')->unsigned();
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->integer('usuario_id')->unsigned()->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('set null');
            $table->string('status', 20)->default('rascunho');
            $table->string('observacao', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('rota_entrega_itens', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('rota_entrega_id')->unsigned();
            $table->foreign('rota_entrega_id')->references('id')->on('rotas_entrega')->onDelete('cascade');
            $table->integer('venda_id')->unsigned();
            $table->foreign('venda_id')->references('id')->on('vendas')->onDelete('cascade');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->string('cliente_nome', 255);
            $table->string('telefone', 40)->nullable();
            $table->string('rua', 255)->nullable();
            $table->string('numero', 30)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('complemento', 255)->nullable();
            $table->decimal('valor_total', 16, 2)->default(0);
            $table->decimal('frete', 10, 2)->default(0);
            $table->unsignedSmallInteger('qtd_parcelas')->default(1);
            $table->boolean('mostrar_parcelas')->default(false);
            $table->boolean('prioridade')->default(false);
            $table->string('observacao_prioridade', 500)->nullable();
            $table->string('status_entrega', 20)->default('pendente');
            $table->string('ocorrencia_tipo', 60)->nullable();
            $table->text('ocorrencia_observacao')->nullable();
            $table->string('token_confirmacao', 64)->unique();
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();

            $table->index(['venda_id', 'status_entrega']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rota_entrega_itens');
        Schema::dropIfExists('rotas_entrega');
    }
};
