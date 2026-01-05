<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->integer('porcentagem_insalubridade')->nullable()->after('valor')
                ->comment('Porcentagem de insalubridade: 10, 20 ou 40');
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropColumn('porcentagem_insalubridade');
        });
    }
};
