<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->string('competencia', 7)->after('setor_origem_id')->comment('Formato YYYY-MM');
            $table->index('competencia');
            $table->index(['servidor_id', 'evento_id', 'competencia'], 'idx_lancamento_duplicidade');
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropIndex('idx_lancamento_duplicidade');
            $table->dropIndex(['competencia']);
            $table->dropColumn('competencia');
        });
    }
};
