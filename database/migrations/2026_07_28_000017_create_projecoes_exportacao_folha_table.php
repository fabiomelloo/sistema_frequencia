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
            $table->boolean('gera_efeito_financeiro')->default(false)->after('origem_informacao');
            $table->boolean('regra_validada_snapshot')->default(false)->after('gera_efeito_financeiro');
        });

        DB::table('folha_frequencia_itens')
            ->select(['id', 'evento_id'])
            ->whereNotNull('evento_id')
            ->orderBy('id')
            ->chunkById(500, function ($itens): void {
                $eventos = DB::table('eventos_folha')
                    ->whereIn('id', $itens->pluck('evento_id')->unique())
                    ->get(['id', 'gera_efeito_financeiro', 'regra_validada'])
                    ->keyBy('id');

                foreach ($itens as $item) {
                    $evento = $eventos->get($item->evento_id);
                    if (! $evento) {
                        continue;
                    }

                    DB::table('folha_frequencia_itens')->where('id', $item->id)->update([
                        'gera_efeito_financeiro' => (bool) $evento->gera_efeito_financeiro,
                        'regra_validada_snapshot' => (bool) $evento->regra_validada,
                    ]);
                }
            });

        DB::table('folha_frequencia_itens')->whereNull('evento_id')->update([
            'gera_efeito_financeiro' => true,
            'regra_validada_snapshot' => false,
        ]);

        Schema::create('projecoes_exportacao_folha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folha_frequencia_item_id')->unique()
                ->constrained('folha_frequencia_itens')->restrictOnDelete();
            $table->foreignId('folha_frequencia_id')->constrained('folhas_frequencia')->restrictOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->unsignedInteger('rodada_conferencia');
            $table->foreignId('servidor_id')->nullable()->constrained('servidores')->nullOnDelete();
            $table->string('codigo_evento', 10);
            $table->string('matricula', 14);
            $table->decimal('valor', 12, 2);
            $table->string('status', 20)->default('PRONTA');
            $table->foreignId('exportacao_id')->nullable()->constrained('exportacoes_folha')->nullOnDelete();
            $table->timestamp('exportado_em')->nullable();
            $table->timestamps();

            $table->index(['competencia_id', 'status'], 'projecao_competencia_status_idx');
            $table->index(['folha_frequencia_id', 'rodada_conferencia'], 'projecao_folha_rodada_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projecoes_exportacao_folha');

        Schema::table('folha_frequencia_itens', function (Blueprint $table) {
            $table->dropColumn(['gera_efeito_financeiro', 'regra_validada_snapshot']);
        });
    }
};
