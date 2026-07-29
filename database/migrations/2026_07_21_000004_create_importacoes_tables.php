<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->constrained('setores');
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('nome_original');
            $table->string('caminho_arquivo');
            $table->char('hash_arquivo', 64);
            $table->string('tipo_arquivo', 20)->default('CSV');
            $table->string('status', 30)->default('PENDENTE');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('linhas_validas')->default(0);
            $table->unsignedInteger('linhas_invalidas')->default(0);
            $table->timestamp('processada_em')->nullable();
            $table->timestamps();

            $table->index(['setor_id', 'status']);
            $table->index('hash_arquivo');
        });

        Schema::create('importacao_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacao_id')->constrained('importacoes')->cascadeOnDelete();
            $table->unsignedInteger('numero_linha');
            $table->text('conteudo_original');
            $table->json('dados')->nullable();
            $table->string('status', 30);
            $table->json('erros')->nullable();
            $table->foreignId('lancamento_id')->nullable()->constrained('lancamentos_setoriais')->nullOnDelete();
            $table->timestamps();

            $table->unique(['importacao_id', 'numero_linha']);
            $table->index(['importacao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacao_linhas');
        Schema::dropIfExists('importacoes');
    }
};
