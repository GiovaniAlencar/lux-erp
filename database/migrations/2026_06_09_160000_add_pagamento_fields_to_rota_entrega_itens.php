<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rota_entrega_itens', function (Blueprint $table) {
            $table->boolean('mostrar_valor_pedido')->default(false)->after('mostrar_parcelas');
            $table->string('situacao_pagamento', 30)->default('pagamento_entrega')->after('mostrar_valor_pedido');
            $table->decimal('valor_restante', 16, 2)->nullable()->after('situacao_pagamento');
            $table->decimal('valor_parcela', 16, 2)->nullable()->after('valor_restante');
            $table->string('tipo_pagamento', 2)->nullable()->after('valor_parcela');
            $table->string('tipo_pagamento_nome', 80)->nullable()->after('tipo_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('rota_entrega_itens', function (Blueprint $table) {
            $table->dropColumn([
                'mostrar_valor_pedido',
                'situacao_pagamento',
                'valor_restante',
                'valor_parcela',
                'tipo_pagamento',
                'tipo_pagamento_nome',
            ]);
        });
    }
};
