<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContextoToAlteracaoEstoquesTable extends Migration
{
    public function up()
    {
        Schema::table('alteracao_estoques', function (Blueprint $table) {
            $table->string('acao', 60)->nullable()->after('tipo');
            $table->string('origem', 60)->nullable()->after('acao');
            $table->integer('origem_id')->nullable()->after('origem');

            $table->integer('pedido_id')->nullable()->unsigned()->after('origem_id');
            $table->integer('item_pedido_id')->nullable()->unsigned()->after('pedido_id');

            $table->decimal('estoque_anterior', 10, 3)->nullable()->after('observacao');
            $table->decimal('estoque_novo', 10, 3)->nullable()->after('estoque_anterior');
        });
    }

    public function down()
    {
        Schema::table('alteracao_estoques', function (Blueprint $table) {
            $table->dropColumn('acao');
            $table->dropColumn('origem');
            $table->dropColumn('origem_id');
            $table->dropColumn('pedido_id');
            $table->dropColumn('item_pedido_id');
            $table->dropColumn('estoque_anterior');
            $table->dropColumn('estoque_novo');
        });
    }
}
