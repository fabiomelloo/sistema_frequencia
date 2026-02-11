<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            if (!Schema::hasColumn('servidores', 'data_admissao')) {
                $table->date('data_admissao')->nullable()->after('trabalha_noturno');
            }
            if (!Schema::hasColumn('servidores', 'data_desligamento')) {
                $table->date('data_desligamento')->nullable()->after('data_admissao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn(['data_admissao', 'data_desligamento']);
        });
    }
};
