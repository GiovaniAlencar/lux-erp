<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotas_entrega', function (Blueprint $table) {
            $table->boolean('motoboy_pago')->default(false)->after('motoboy_nome');
            $table->timestamp('motoboy_pago_em')->nullable()->after('motoboy_pago');
            $table->unsignedInteger('motoboy_pago_usuario_id')->nullable()->after('motoboy_pago_em');
            $table->foreign('motoboy_pago_usuario_id')->references('id')->on('usuarios')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('rotas_entrega', function (Blueprint $table) {
            $table->dropForeign(['motoboy_pago_usuario_id']);
            $table->dropColumn(['motoboy_pago', 'motoboy_pago_em', 'motoboy_pago_usuario_id']);
        });
    }
};
