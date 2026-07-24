<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folha_frequencia_itens', function (Blueprint $table) {
            $table->boolean('exige_documento')->default(false)->after('referencia_documento');
        });

        DB::statement('UPDATE folha_frequencia_itens AS item INNER JOIN eventos_folha AS evento ON evento.id = item.evento_id SET item.exige_documento = evento.exige_documento');

        Schema::create('evidencias_documentais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ocorrencia_frequencia_id')->nullable();
            $table->foreignId('folha_frequencia_item_id')->nullable();
            $table->string('nome_original', 255);
            $table->string('caminho_arquivo', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanho_bytes');
            $table->char('hash_sha256', 64);
            $table->string('status', 20)->default('PENDENTE');
            $table->text('descricao')->nullable();
            $table->text('motivo_revisao')->nullable();
            $table->foreignId('enviado_por_id');
            $table->foreignId('revisado_por_id')->nullable();
            $table->timestamp('revisado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ocorrencia_frequencia_id', 'evidencia_ocorrencia_fk')
                ->references('id')->on('ocorrencias_frequencia')->cascadeOnDelete();
            $table->foreign('folha_frequencia_item_id', 'evidencia_item_fk')
                ->references('id')->on('folha_frequencia_itens')->cascadeOnDelete();
            $table->foreign('enviado_por_id', 'evidencia_enviado_por_fk')
                ->references('id')->on('users');
            $table->foreign('revisado_por_id', 'evidencia_revisado_por_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['ocorrencia_frequencia_id', 'status'], 'evidencia_ocorrencia_status_idx');
            $table->index(['folha_frequencia_item_id', 'status'], 'evidencia_item_status_idx');
            $table->index('hash_sha256', 'evidencia_hash_idx');
        });

        DB::statement('ALTER TABLE evidencias_documentais ADD CONSTRAINT evidencia_alvo_check CHECK ((ocorrencia_frequencia_id IS NULL) <> (folha_frequencia_item_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias_documentais');

        Schema::table('folha_frequencia_itens', function (Blueprint $table) {
            $table->dropColumn('exige_documento');
        });
    }
};
