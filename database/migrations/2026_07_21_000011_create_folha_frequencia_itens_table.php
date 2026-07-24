<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folha_frequencia_servidores', function (Blueprint $table) {
            $table->foreignId('vinculo_funcional_id')->nullable()->after('servidor_id')
                ->constrained('vinculos_funcionais')->nullOnDelete();
            $table->json('designacoes_snapshot')->nullable()->after('carga_horaria');
            $table->boolean('mudanca_funcional_no_periodo')->default(false)->after('designacoes_snapshot');
        });

        Schema::create('folha_frequencia_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folha_frequencia_servidor_id')->constrained('folha_frequencia_servidores')->cascadeOnDelete();
            $table->foreignId('evento_id')->nullable()->constrained('eventos_folha')->nullOnDelete();
            $table->foreignId('vantagem_funcional_id')->nullable()->constrained('vantagens_funcionais')->nullOnDelete();
            $table->string('codigo_evento', 50);
            $table->string('sigla', 20)->nullable();
            $table->string('descricao');
            $table->string('unidade_lancamento', 30);
            $table->string('origem_informacao', 30);
            $table->decimal('percentual', 7, 2)->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->decimal('quantidade', 8, 2)->nullable();
            $table->string('nivel', 30)->nullable();
            $table->text('texto')->nullable();
            $table->text('observacao')->nullable();
            $table->string('referencia_documento', 255)->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('editavel_pelo_setor')->default(true);
            $table->foreignId('atualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['folha_frequencia_servidor_id', 'origem_informacao'], 'folha_item_origem_idx');
            $table->index(['folha_frequencia_servidor_id', 'evento_id'], 'folha_item_evento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folha_frequencia_itens');

        Schema::table('folha_frequencia_servidores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vinculo_funcional_id');
            $table->dropColumn(['designacoes_snapshot', 'mudanca_funcional_no_periodo']);
        });
    }
};
