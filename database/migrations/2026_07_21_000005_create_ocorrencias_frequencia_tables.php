<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocorrencias_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('setor_id')->constrained('setores');
            $table->foreignId('competencia_id')->constrained('competencias');
            $table->string('tipo', 40);
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('justificada')->nullable();
            $table->boolean('possui_comprovacao')->default(false);
            $table->string('referencia_documento')->nullable();
            $table->text('observacao_original')->nullable();
            $table->string('origem', 20)->default('MANUAL');
            $table->foreignId('importacao_linha_id')->nullable()->constrained('importacao_linhas')->nullOnDelete();
            $table->foreignId('criado_por_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['setor_id', 'competencia_id']);
            $table->index(['servidor_id', 'competencia_id']);
            $table->index(['tipo', 'data_inicio']);
        });

        Schema::create('ocorrencia_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ocorrencia_id')->constrained('ocorrencias_frequencia')->cascadeOnDelete();
            $table->date('data');
            $table->timestamps();

            $table->unique(['ocorrencia_id', 'data']);
            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencia_dias');
        Schema::dropIfExists('ocorrencias_frequencia');
    }
};
