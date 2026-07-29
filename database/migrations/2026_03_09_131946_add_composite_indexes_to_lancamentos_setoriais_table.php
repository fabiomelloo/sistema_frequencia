<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->index(['setor_origem_id', 'competencia', 'status'], 'idx_setor_comp_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropIndex('idx_setor_comp_status');
        });
    }
};
