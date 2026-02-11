<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Passo 1: Adicionar coluna como nullable (funciona com dados existentes)
        if (!Schema::hasColumn('lancamentos_setoriais', 'competencia')) {
            Schema::table('lancamentos_setoriais', function (Blueprint $table) {
                $table->string('competencia', 7)->nullable();
            });
        }

        // Passo 2: Preencher registros existentes com mês atual
        DB::table('lancamentos_setoriais')
            ->whereNull('competencia')
            ->update(['competencia' => now()->format('Y-m')]);

        // Passo 3: Tornar NOT NULL (raw SQL para compatibilidade PostgreSQL)
        DB::statement('ALTER TABLE lancamentos_setoriais ALTER COLUMN competencia SET NOT NULL');

        // Passo 4: Índices (verifica se já existem)
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
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
