<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Renomear coluna dias_lancados para dias_trabalhados
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->renameColumn('dias_lancados', 'dias_trabalhados');
        });

        // Atualizar constraints que referenciam dias_lancados
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            DROP CONSTRAINT IF EXISTS chk_dias_positivos
        ");

        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            DROP CONSTRAINT IF EXISTS chk_dias_noturnos_coerentes
        ");

        // Recriar constraints com novo nome
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_positivos 
            CHECK (
                (dias_trabalhados IS NULL OR dias_trabalhados >= 0)
                AND (dias_noturnos IS NULL OR dias_noturnos >= 0)
            )
        ");

        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_noturnos_coerentes 
            CHECK (
                dias_noturnos IS NULL 
                OR dias_trabalhados IS NULL 
                OR dias_noturnos <= dias_trabalhados
            )
        ");
    }

    public function down(): void
    {
        // Remover constraints
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_dias_noturnos_coerentes");
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS chk_dias_positivos");

        // Renomear de volta
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->renameColumn('dias_trabalhados', 'dias_lancados');
        });

        // Recriar constraints antigas
        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_positivos 
            CHECK (
                (dias_lancados IS NULL OR dias_lancados >= 0)
                AND (dias_noturnos IS NULL OR dias_noturnos >= 0)
            )
        ");

        DB::statement("
            ALTER TABLE lancamentos_setoriais 
            ADD CONSTRAINT chk_dias_noturnos_coerentes 
            CHECK (
                dias_noturnos IS NULL 
                OR dias_lancados IS NULL 
                OR dias_noturnos <= dias_lancados
            )
        ");
    }
};
