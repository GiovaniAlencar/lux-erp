<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotas_entrega', function (Blueprint $table) {
            $table->string('motoboy_nome', 120)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rotas_entrega', function (Blueprint $table) {
            $table->dropColumn('motoboy_nome');
        });
    }
};
