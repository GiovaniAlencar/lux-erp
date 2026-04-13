<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddStatusPedidoPagamentoToVendasTable extends Migration
{
    public function up()
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('status_pedido', 30)->default('em_elaboracao')->after('estado_emissao');
            $table->string('status_pagamento', 30)->default('pendente')->after('status_pedido');
        });

        DB::table('vendas')->where('estado_emissao', 'aprovado')->update(['status_pedido' => 'finalizada']);
        DB::table('vendas')->where('estado_emissao', 'cancelado')->update(['status_pedido' => 'cancelada']);
        DB::table('vendas')->whereIn('estado_emissao', ['novo', 'rejeitado'])->update(['status_pedido' => 'em_elaboracao']);

        DB::table('vendas')->where('forma_pagamento', 'a_vista')->update(['status_pagamento' => 'pago']);
        DB::table('vendas')->where('forma_pagamento', 'conta_crediario')->update(['status_pagamento' => 'pendente']);
    }

    public function down()
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('status_pedido');
            $table->dropColumn('status_pagamento');
        });
    }
}
