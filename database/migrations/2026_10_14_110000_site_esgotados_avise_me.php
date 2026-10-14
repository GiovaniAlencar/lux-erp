<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('produtos', 'ocultar_site')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->boolean('ocultar_site')->default(false);
            });
        }
        if (!Schema::hasTable('ecommerce_avisos_reposicao')) {
            Schema::create('ecommerce_avisos_reposicao', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ecommerce_user_id');
                $table->unsignedInteger('produto_id');
                $table->dateTime('avisado_em')->nullable();
                $table->timestamps();
                $table->unique(['ecommerce_user_id', 'produto_id'], 'uq_aviso_user_produto');
                $table->index(['produto_id', 'avisado_em'], 'idx_aviso_produto_pendente');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_avisos_reposicao');
        if (Schema::hasColumn('produtos', 'ocultar_site')) {
            Schema::table('produtos', fn (Blueprint $t) => $t->dropColumn('ocultar_site'));
        }
    }
};
