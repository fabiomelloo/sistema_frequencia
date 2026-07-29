<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importacoes', function (Blueprint $table) {
            $table->string('finalidade', 30)->default('LANCAMENTOS')->after('tipo_arquivo');
            $table->index(['setor_id', 'finalidade', 'status']);
        });

        Schema::table('importacao_linhas', function (Blueprint $table) {
            $table->foreignId('ocorrencia_id')
                ->nullable()
                ->after('lancamento_id')
                ->constrained('ocorrencias_frequencia')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('importacao_linhas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ocorrencia_id');
        });

        Schema::table('importacoes', function (Blueprint $table) {
            $table->dropIndex(['setor_id', 'finalidade', 'status']);
            $table->dropColumn('finalidade');
        });
    }
};
