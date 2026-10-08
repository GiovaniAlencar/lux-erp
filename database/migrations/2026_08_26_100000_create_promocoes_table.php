<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id')->unsigned();
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->integer('produto_id')->unsigned();
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->string('tipo', 20)->default('percentual'); // percentual | preco_fixo
            $table->decimal('valor', 12, 4);
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->unsignedInteger('quantidade_limite')->nullable();
            $table->unsignedInteger('quantidade_utilizada')->default(0);
            $table->boolean('ativo')->default(true);
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->index(['produto_id', 'ativo']);
            $table->index(['data_inicio', 'data_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocoes');
    }
};
