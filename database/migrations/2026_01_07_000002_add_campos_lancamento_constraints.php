<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            // Campos faltantes
            $table->integer('porcentagem_periculosidade')->nullable()
                ->after('porcentagem_insalubridade')
                ->comment('Porcentagem de periculosidade (não pode coexistir com insalubridade)');
            
            $table->decimal('adicional_turno', 10, 2)->nullable()
                ->after('valor')
                ->comment('Adicional de turno (apenas para vigia)');
            
            $table->decimal('adicional_noturno', 10, 2)->nullable()
                ->after('adicional_turno')
                ->comment('Adicional noturno (apenas quando trabalha noturno)');
            
            $table->integer('dias_noturnos')->nullable()
                ->after('dias_lancados')
                ->comment('Dias trabalhados em período noturno (para adicional noturno)');
        });

        // Constraint crítica: Insalubridade e Periculosidade não podem coexistir
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_insalubridade_periculosidade 
            CHECK (
                NOT (
                    porcentagem_insalubridade IS NOT NULL 
                    AND porcentagem_periculosidade IS NOT NULL
                )
            )
        ");

        // Constraint: Dias não podem ser negativos
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_positivos 
            CHECK (
                (dias_lancados IS NULL OR dias_lancados >= 0)
                AND (dias_noturnos IS NULL OR dias_noturnos >= 0)
            )
        ");

        // Constraint: Dias noturnos não podem ser maiores que dias lançados
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_noturnos_coerentes 
            CHECK (
                dias_noturnos IS NULL 
                OR dias_lancados IS NULL 
                OR dias_noturnos <= dias_lancados
            )
        ");

        // Constraint: Valores não podem ser negativos
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_valores_positivos 
            CHECK (
                (valor IS NULL OR valor >= 0)
                AND (adicional_turno IS NULL OR adicional_turno >= 0)
                AND (adicional_noturno IS NULL OR adicional_noturno >= 0)
            )
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_valores_positivos");
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_dias_noturnos_coerentes");
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_dias_positivos");
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_insalubridade_periculosidade");

        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropColumn([
                'porcentagem_periculosidade',
                'adicional_turno',
                'adicional_noturno',
                'dias_noturnos',
            ]);
        });
    }
};
