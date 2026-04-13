<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusPagamentoToPedidosTable extends Migration
{
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('status_pedido', 30)->default('aberto')->after('status');
            $table->string('status_pagamento', 30)->default('pendente')->after('status_pedido');
        });
    }

    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('status_pedido');
            $table->dropColumn('status_pagamento');
        });
    }
}
