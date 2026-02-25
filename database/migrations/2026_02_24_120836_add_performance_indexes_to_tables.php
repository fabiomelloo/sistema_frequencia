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
            $table->index(['competencia', 'status'], 'idx_lanc_comp_stat');
            $table->index('servidor_id', 'idx_lanc_servidor');
            $table->index('setor_origem_id', 'idx_lanc_setor_origem');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['modelo', 'modelo_id'], 'idx_audit_modelo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_modelo_id');
        });

        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropIndex('idx_lanc_comp_stat');
            $table->dropIndex('idx_lanc_servidor');
            $table->dropIndex('idx_lanc_setor_origem');
        });
    }
};
