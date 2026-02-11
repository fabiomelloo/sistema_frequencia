<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotacao_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('setor_id')->constrained('setores');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'data_inicio']);
            $table->index('setor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotacao_historico');
    }
};
