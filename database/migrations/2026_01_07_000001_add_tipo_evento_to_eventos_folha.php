<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->string('tipo_evento', 30)->nullable()->after('codigo_evento')
                ->comment('Tipo do evento para regras de negócio (desacopla de codigo_evento)');
        });

        // Atualizar eventos existentes baseado no código (migração de dados)
        // Isso é uma estimativa - ajuste conforme seus dados reais
        $sql = <<<'SQL'
            UPDATE eventos_folha 
            SET tipo_evento = CASE
                WHEN codigo_evento LIKE '%TURNO%' OR codigo_evento LIKE '%TURN%' THEN 'ADICIONAL_TURNO'
                WHEN codigo_evento LIKE '%NOTURNO%' OR codigo_evento LIKE '%NOTURN%' THEN 'ADICIONAL_NOTURNO'
                WHEN codigo_evento LIKE '%INSALUBRIDADE%' OR codigo_evento LIKE '%INSAL%' THEN 'INSALUBRIDADE'
                WHEN codigo_evento LIKE '%PERICULOSIDADE%' OR codigo_evento LIKE '%PERIC%' THEN 'PERICULOSIDADE'
                WHEN codigo_evento LIKE '%GRATIFICACAO%' OR codigo_evento LIKE '%GRAT%' THEN 'GRATIFICACAO'
                WHEN codigo_evento LIKE '%FREQUENCIA%' OR codigo_evento LIKE '%FREQ%' THEN 'FREQUENCIA'
                ELSE 'OUTROS'
            END
            WHERE tipo_evento IS NULL
        SQL;
        
        DB::statement($sql);

        // Tornar obrigatório após migração
        DB::statement('ALTER TABLE eventos_folha ALTER COLUMN tipo_evento SET NOT NULL');

        // Adicionar índice para performance
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->index('tipo_evento');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->dropIndex(['tipo_evento']);
            $table->dropColumn('tipo_evento');
        });
    }
};
