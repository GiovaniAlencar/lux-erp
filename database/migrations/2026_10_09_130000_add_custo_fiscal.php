<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Custo médio só das unidades com nota (custo fiscal) + custo unitário em cada movimento fiscal. */
class AddCustoFiscal extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('produtos', 'custo_fiscal')) {
            Schema::table('produtos', fn (Blueprint $t) => $t->decimal('custo_fiscal', 14, 4)->default(0)->after('estoque_fiscal'));
        }
        if (!Schema::hasColumn('estoque_fiscal_movimentos', 'custo_unitario')) {
            Schema::table('estoque_fiscal_movimentos', fn (Blueprint $t) => $t->decimal('custo_unitario', 14, 4)->nullable()->after('saldo_apos'));
        }
    }

    public function down()
    {
        if (Schema::hasColumn('estoque_fiscal_movimentos', 'custo_unitario')) {
            Schema::table('estoque_fiscal_movimentos', fn (Blueprint $t) => $t->dropColumn('custo_unitario'));
        }
        if (Schema::hasColumn('produtos', 'custo_fiscal')) {
            Schema::table('produtos', fn (Blueprint $t) => $t->dropColumn('custo_fiscal'));
        }
    }
}
