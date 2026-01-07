<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportacoes_folha', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 6)->comment('Período no formato YYYYMM (ex: 202601)');
            $table->string('nome_arquivo', 255)->comment('Nome do arquivo exportado');
            $table->string('hash_arquivo', 64)->comment('SHA-256 do arquivo para integridade');
            $table->foreignId('usuario_id')->constrained('users')->comment('Usuário que realizou a exportação');
            $table->integer('quantidade_lancamentos')->default(0)->comment('Quantidade de lançamentos exportados');
            $table->timestamp('data_exportacao')->useCurrent();
            $table->timestamps();

            $table->index('periodo');
            $table->index('usuario_id');
            $table->index('data_exportacao');
        });

        // Tabela pivot para relacionar exportações com lançamentos
        Schema::create('exportacao_lancamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exportacao_id')->constrained('exportacoes_folha')->onDelete('cascade');
            $table->foreignId('lancamento_id')->constrained('lancamentos_setoriais')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['exportacao_id', 'lancamento_id'], 'exportacao_lancamento_unique');
            $table->index('lancamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportacao_lancamento');
        Schema::dropIfExists('exportacoes_folha');
    }
};
