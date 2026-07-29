<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folhas_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->constrained('setores');
            $table->foreignId('competencia_id')->constrained('competencias');
            $table->string('status', 20)->default('RASCUNHO');
            $table->foreignId('criado_por_id')->constrained('users');
            $table->foreignId('finalizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();

            $table->unique(['setor_id', 'competencia_id']);
            $table->index(['setor_id', 'status']);
        });

        Schema::create('folha_frequencia_servidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folha_frequencia_id')->constrained('folhas_frequencia')->cascadeOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->string('matricula');
            $table->string('nome');
            $table->string('cargo')->nullable();
            $table->string('vinculo', 30)->nullable();
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->string('status', 20)->default('PENDENTE');
            $table->text('observacao_geral')->nullable();
            $table->foreignId('atualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('preenchida_em')->nullable();
            $table->timestamps();

            $table->unique(['folha_frequencia_id', 'servidor_id'], 'folha_servidor_unique');
            $table->index(['folha_frequencia_id', 'status'], 'folha_servidor_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folha_frequencia_servidores');
        Schema::dropIfExists('folhas_frequencia');
    }
};
