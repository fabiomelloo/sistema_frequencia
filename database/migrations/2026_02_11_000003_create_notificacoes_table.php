<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('tipo', 50); // APROVADO, REJEITADO, EXPORTADO, ALERTA
            $table->string('titulo');
            $table->text('mensagem');
            $table->string('link')->nullable();
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'lida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
