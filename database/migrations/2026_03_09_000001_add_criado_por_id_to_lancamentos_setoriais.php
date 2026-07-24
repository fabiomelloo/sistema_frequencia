<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->unsignedBigInteger('criado_por_id')->nullable()->after('setor_origem_id');
            $table->foreign('criado_por_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos_setoriais', function (Blueprint $table) {
            $table->dropForeign(['criado_por_id']);
            $table->dropColumn('criado_por_id');
        });
    }
};
