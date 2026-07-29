<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folhas_frequencia', function (Blueprint $table) {
            $table->unsignedInteger('rodada_conferencia')->default(0)->after('status');
        });

        Schema::create('conferencias_frequencia_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folha_frequencia_servidor_id');
            $table->unsignedInteger('rodada');
            $table->string('status', 20);
            $table->text('apontamento')->nullable();
            $table->foreignId('conferido_por_id');
            $table->timestamp('conferido_em');
            $table->timestamps();

            $table->unique(['folha_frequencia_servidor_id', 'rodada'], 'conferencia_servidor_rodada_unique');
            $table->index(['rodada', 'status'], 'conferencia_rodada_status_idx');
            $table->foreign('folha_frequencia_servidor_id', 'conf_freq_servidor_fk')
                ->references('id')
                ->on('folha_frequencia_servidores')
                ->cascadeOnDelete();
            $table->foreign('conferido_por_id', 'conf_freq_usuario_fk')
                ->references('id')
                ->on('users');
        });

        DB::table('folhas_frequencia')
            ->whereIn('status', ['FINALIZADA', 'APROVADA', 'DEVOLVIDA'])
            ->update(['rodada_conferencia' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('conferencias_frequencia_servidor');

        Schema::table('folhas_frequencia', function (Blueprint $table) {
            $table->dropColumn('rodada_conferencia');
        });
    }
};
