<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remover antes da renomeação: MySQL 8.4 não permite renomear
        // colunas ainda referenciadas por CHECK constraints.
        $this->removerCheck('chk_dias_positivos');
        $this->removerCheck('chk_dias_noturnos_coerentes');

        if (Schema::hasColumn('lancamentos_setoriais', 'dias_lancados')) {
            Schema::table('lancamentos_setoriais', function (Blueprint $table) {
                $table->renameColumn('dias_lancados', 'dias_trabalhados');
            });
        }

        // Recriar constraints com novo nome
        DB::statement('
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_positivos 
            CHECK (
                (dias_trabalhados IS NULL OR dias_trabalhados >= 0)
                AND (dias_noturnos IS NULL OR dias_noturnos >= 0)
            )
        ');

        DB::statement('
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_noturnos_coerentes 
            CHECK (
                dias_noturnos IS NULL 
                OR dias_trabalhados IS NULL 
                OR dias_noturnos <= dias_trabalhados
            )
        ');
    }

    public function down(): void
    {

        // Remover constraints
        $this->removerCheck('chk_dias_noturnos_coerentes');
        $this->removerCheck('chk_dias_positivos');

        // Renomear de volta
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->renameColumn('dias_trabalhados', 'dias_lancados');
        });

        // Recriar constraints antigas
        DB::statement('
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_positivos 
            CHECK (
                (dias_lancados IS NULL OR dias_lancados >= 0)
                AND (dias_noturnos IS NULL OR dias_noturnos >= 0)
            )
        ');

        DB::statement('
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_noturnos_coerentes 
            CHECK (
                dias_noturnos IS NULL 
                OR dias_lancados IS NULL 
                OR dias_noturnos <= dias_lancados
            )
        ');
    }

    private function removerCheck(string $nome): void
    {
        $operacao = DB::getDriverName() === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';

        try {
            DB::statement("ALTER TABLE lancamentos_setoriais {$operacao} {$nome}");
        } catch (Throwable) {
            // Compatibilidade com bancos antigos nos quais a constraint não existe.
        }
    }
};
