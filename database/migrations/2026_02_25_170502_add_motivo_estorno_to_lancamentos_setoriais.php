<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->text('motivo_estorno')->nullable()->after('motivo_rejeicao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropColumn('motivo_estorno');
        });
    }
};
