<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->unsignedInteger('versao_pedido')->default(1)->after('status_pagamento');
            $table->unsignedInteger('versao_ficha_impressa')->nullable()->after('versao_pedido');
            $table->unsignedInteger('versao_pedido_pdf')->nullable()->after('versao_ficha_impressa');
            $table->timestamp('ficha_impressa_em')->nullable()->after('versao_pedido_pdf');
            $table->timestamp('pedido_pdf_em')->nullable()->after('ficha_impressa_em');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn([
                'versao_pedido',
                'versao_ficha_impressa',
                'versao_pedido_pdf',
                'ficha_impressa_em',
                'pedido_pdf_em',
            ]);
        });
    }
};
