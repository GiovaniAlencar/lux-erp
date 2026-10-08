<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle da NF-e emitida no outro sistema (parte fiscal da venda).
 * nf_externa_status: null/pendente | emitida
 * nf_externa_valor: valor fiscal no momento em que foi marcada como emitida
 *                   (se a venda mudar depois, a tela avisa que não bate).
 */
class AddNfExternaToVendas extends Migration
{
    public function up()
    {
        Schema::table('vendas', function (Blueprint $table) {
            if (!Schema::hasColumn('vendas', 'nf_externa_status')) {
                $table->string('nf_externa_status', 20)->nullable();
                $table->string('nf_externa_numero', 30)->nullable();
                $table->decimal('nf_externa_valor', 16, 2)->nullable();
                $table->timestamp('nf_externa_em')->nullable();
                $table->unsignedInteger('nf_externa_usuario_id')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn([
                'nf_externa_status',
                'nf_externa_numero',
                'nf_externa_valor',
                'nf_externa_em',
                'nf_externa_usuario_id',
            ]);
        });
    }
}
