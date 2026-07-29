<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folhas_frequencia', function (Blueprint $table) {
            $table->foreignId('conferido_por_id')->nullable()->after('finalizada_em')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('conferida_em')->nullable()->after('conferido_por_id');
            $table->text('motivo_devolucao')->nullable()->after('conferida_em');
        });
    }

    public function down(): void
    {
        Schema::table('folhas_frequencia', function (Blueprint $table) {
            $table->dropForeign(['conferido_por_id']);
            $table->dropColumn(['conferido_por_id', 'conferida_em', 'motivo_devolucao']);
        });
    }
};
