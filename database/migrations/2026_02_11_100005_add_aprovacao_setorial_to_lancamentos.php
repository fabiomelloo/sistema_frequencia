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
            $table->foreignId('conferido_setorial_por')->nullable()->after('id_validador')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('conferido_setorial_em')->nullable()->after('conferido_setorial_por');
        });

        // Expandir enum de status para incluir CONFERIDO_SETORIAL (PostgreSQL)
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS lancamentos_setoriais_status_check");
        DB::statement("ALTER TABLE lancamentos_setoriais ADD CONSTRAINT lancamentos_setoriais_status_check CHECK (status::text = ANY (ARRAY['PENDENTE', 'CONFERIDO_SETORIAL', 'CONFERIDO', 'REJEITADO', 'EXPORTADO', 'ESTORNADO']::text[]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS lancamentos_setoriais_status_check");
        DB::statement("ALTER TABLE lancamentos_setoriais ADD CONSTRAINT lancamentos_setoriais_status_check CHECK (status::text = ANY (ARRAY['PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO']::text[]))");

        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropForeign(['conferido_setorial_por']);
            $table->dropColumn(['conferido_setorial_por', 'conferido_setorial_em']);
        });
    }
};
