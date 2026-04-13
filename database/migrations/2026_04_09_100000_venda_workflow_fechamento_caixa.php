<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class VendaWorkflowFechamentoCaixa extends Migration
{
    public function up()
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->boolean('fechada_caixa')->default(false)->after('status_pagamento');
            $table->timestamp('fechada_em')->nullable()->after('fechada_caixa');
            $table->unsignedInteger('fechada_por_usuario_id')->nullable()->after('fechada_em');
            $table->foreign('fechada_por_usuario_id')->references('id')->on('usuarios')->onDelete('set null');
        });

        DB::table('vendas')->whereIn('status_pedido', ['em_elaboracao'])->update(['status_pedido' => 'aguardando_confirmacao']);
        DB::table('vendas')->where('status_pedido', 'finalizada')->update(['status_pedido' => 'confirmado']);
    }

    public function down()
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropForeign(['fechada_por_usuario_id']);
            $table->dropColumn(['fechada_caixa', 'fechada_em', 'fechada_por_usuario_id']);
        });
    }
}
