<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setores', function (Blueprint $table) {
            $table->string('codigo_externo', 50)->nullable()->after('sigla');
            $table->foreignId('setor_pai_id')
                ->nullable()
                ->after('codigo_externo')
                ->constrained('setores')
                ->nullOnDelete();
            $table->index('codigo_externo');
        });
    }

    public function down(): void
    {
        Schema::table('setores', function (Blueprint $table) {
            $table->dropForeign(['setor_pai_id']);
            $table->dropIndex(['codigo_externo']);
            $table->dropColumn(['codigo_externo', 'setor_pai_id']);
        });
    }
};
