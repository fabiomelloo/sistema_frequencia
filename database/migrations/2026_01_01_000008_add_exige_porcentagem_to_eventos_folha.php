<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->boolean('exige_porcentagem')->default(false)->after('exige_observacao')
                ->comment('Se o evento exige campo de porcentagem (ex: insalubridade)');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->dropColumn('exige_porcentagem');
        });
    }
};
