<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePedidoAuditoriasTable extends Migration
{
    public function up()
    {
        Schema::create('pedido_auditorias', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('empresa_id')->unsigned();
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');

            $table->integer('pedido_id')->unsigned();
            $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');

            $table->integer('usuario_id')->nullable()->unsigned();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('set null');

            $table->string('acao', 60);
            $table->string('descricao', 255);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedido_auditorias');
    }
}
