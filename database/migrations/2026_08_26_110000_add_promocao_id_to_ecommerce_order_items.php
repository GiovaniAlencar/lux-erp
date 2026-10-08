<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_order_items', function (Blueprint $table) {
            $table->integer('promocao_id')->unsigned()->nullable()->after('total_price');
            $table->foreign('promocao_id')->references('id')->on('promocoes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_order_items', function (Blueprint $table) {
            $table->dropForeign(['promocao_id']);
            $table->dropColumn('promocao_id');
        });
    }
};
