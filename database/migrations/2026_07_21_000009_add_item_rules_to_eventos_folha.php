<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->string('sigla', 20)->nullable()->after('codigo_evento');
            $table->string('unidade_lancamento', 30)->nullable()->after('tipo_evento');
            $table->string('origem_informacao', 30)->default('SETOR_MENSAL')->after('unidade_lancamento');
            $table->boolean('gera_efeito_financeiro')->default(true)->after('origem_informacao');
            $table->boolean('exige_documento')->default(false)->after('exige_observacao');
            $table->text('instrucoes_lancamento')->nullable()->after('exige_documento');
            $table->boolean('regra_validada')->default(false)->after('instrucoes_lancamento');
            $table->foreignId('regra_validada_por_id')->nullable()->after('regra_validada')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('regra_validada_em')->nullable()->after('regra_validada_por_id');
        });

        DB::table('eventos_folha')->where('exige_porcentagem', true)
            ->update(['unidade_lancamento' => 'PERCENTUAL']);
        DB::table('eventos_folha')->whereNull('unidade_lancamento')->where('exige_valor', true)
            ->update(['unidade_lancamento' => 'VALOR']);
        DB::table('eventos_folha')->whereNull('unidade_lancamento')->where('exige_dias', true)
            ->update(['unidade_lancamento' => 'DIAS']);
        DB::table('eventos_folha')->whereNull('unidade_lancamento')
            ->update(['unidade_lancamento' => 'MARCADOR']);
    }

    public function down(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->dropForeign(['regra_validada_por_id']);
            $table->dropColumn([
                'sigla', 'unidade_lancamento', 'origem_informacao', 'gera_efeito_financeiro',
                'exige_documento', 'instrucoes_lancamento', 'regra_validada',
                'regra_validada_por_id', 'regra_validada_em',
            ]);
        });
    }
};
