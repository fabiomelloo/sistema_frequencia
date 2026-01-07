<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->boolean('funcao_vigia')->default(false)
                ->after('ativo')
                ->comment('Indica se o servidor exerce função de vigia (necessário para adicional de turno)');
            
            $table->boolean('trabalha_noturno')->default(false)
                ->after('funcao_vigia')
                ->comment('Indica se o servidor trabalha em período noturno (necessário para adicional noturno)');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn(['funcao_vigia', 'trabalha_noturno']);
        });
    }
};
