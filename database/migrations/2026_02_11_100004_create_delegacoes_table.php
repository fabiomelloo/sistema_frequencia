<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegante_id')->constrained('users')->comment('Quem delega');
            $table->foreignId('delegado_id')->constrained('users')->comment('Quem recebe a delegação');
            $table->foreignId('setor_id')->constrained('setores');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->boolean('ativa')->default(true);
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['setor_id', 'ativa']);
            $table->index('delegado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegacoes');
    }
};
