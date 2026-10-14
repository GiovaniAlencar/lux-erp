<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('produtos', 'destaque_site')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->boolean('destaque_site')->default(false);
                $table->integer('destaque_ordem')->default(0);
                $table->date('destaque_ate')->nullable();
                $table->index(['destaque_site', 'destaque_ordem'], 'idx_produtos_destaque_site');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('produtos', 'destaque_site')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropIndex('idx_produtos_destaque_site');
                $table->dropColumn(['destaque_site', 'destaque_ordem', 'destaque_ate']);
            });
        }
    }
};
