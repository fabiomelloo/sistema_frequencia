<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INACTIVE_LANCAMENTO_STATUSES = [
        'REJEITADO',
        'ESTORNADO',
        'CANCELADO',
    ];

    public function up(): void
    {
        $this->assertNoBusinessKeyConflicts();

        Schema::table('lancamentos_setoriais', function (Blueprint $table): void {
            $table->unsignedTinyInteger('business_active_key')
                ->nullable()
                ->storedAs(
                    "CASE WHEN deleted_at IS NULL AND status NOT IN ('REJEITADO', 'ESTORNADO', 'CANCELADO') ".
                    'THEN 1 ELSE NULL END'
                );
            $table->unique(
                ['servidor_id', 'evento_id', 'competencia', 'business_active_key'],
                'lancamentos_business_active_unique'
            );
        });

        Schema::table('folha_frequencia_itens', function (Blueprint $table): void {
            $table->unique(
                ['folha_frequencia_servidor_id', 'evento_id', 'origem_informacao'],
                'folha_itens_monthly_event_unique'
            );
            $table->unique(
                ['folha_frequencia_servidor_id', 'vantagem_funcional_id'],
                'folha_itens_vantagem_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('folha_frequencia_itens', function (Blueprint $table): void {
            $table->dropUnique('folha_itens_monthly_event_unique');
            $table->dropUnique('folha_itens_vantagem_unique');
        });

        Schema::table('lancamentos_setoriais', function (Blueprint $table): void {
            $table->dropUnique('lancamentos_business_active_unique');
            $table->dropColumn('business_active_key');
        });
    }

    private function assertNoBusinessKeyConflicts(): void
    {
        $conflicts = [];

        if (DB::table('lancamentos_setoriais')
            ->selectRaw('1')
            ->whereNull('deleted_at')
            ->whereNotIn('status', self::INACTIVE_LANCAMENTO_STATUSES)
            ->groupBy('servidor_id', 'evento_id', 'competencia')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            $conflicts[] = 'lancamentos_setoriais ativos';
        }

        if (DB::table('folha_frequencia_itens')
            ->selectRaw('1')
            ->where('origem_informacao', 'SETOR_MENSAL')
            ->whereNotNull('evento_id')
            ->groupBy('folha_frequencia_servidor_id', 'evento_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            $conflicts[] = 'itens mensais por evento';
        }

        if (DB::table('folha_frequencia_itens')
            ->selectRaw('1')
            ->whereNotNull('vantagem_funcional_id')
            ->groupBy('folha_frequencia_servidor_id', 'vantagem_funcional_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            $conflicts[] = 'snapshots por vantagem funcional';
        }

        if ($conflicts !== []) {
            throw new RuntimeException(
                'Não foi possível criar as chaves únicas: existem conflitos em '.implode(', ', $conflicts).
                '. Reconcile os registros antes de executar novamente a migration.'
            );
        }
    }
};
