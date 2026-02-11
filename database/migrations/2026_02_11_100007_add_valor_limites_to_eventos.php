<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // valor_minimo e valor_maximo podem já existir no fillable do model
        // mas não necessariamente na tabela — verificar e adicionar se necessário
        Schema::table('eventos_folha', function (Blueprint $table) {
            if (!Schema::hasColumn('eventos_folha', 'valor_minimo')) {
                $table->decimal('valor_minimo', 10, 2)->nullable()->after('exige_porcentagem');
            }
            if (!Schema::hasColumn('eventos_folha', 'valor_maximo')) {
                $table->decimal('valor_maximo', 10, 2)->nullable()->after('valor_minimo');
            }
            if (!Schema::hasColumn('eventos_folha', 'dias_maximo')) {
                $table->integer('dias_maximo')->nullable()->after('valor_maximo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->dropColumn(['valor_minimo', 'valor_maximo', 'dias_maximo']);
        });
    }
};
