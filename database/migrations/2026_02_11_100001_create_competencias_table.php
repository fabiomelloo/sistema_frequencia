<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 7)->unique()->comment('Formato YYYY-MM');
            $table->enum('status', ['ABERTA', 'FECHADA'])->default('ABERTA');
            $table->date('data_limite')->nullable()->comment('Prazo para SETORIAL enviar lançamentos');
            $table->foreignId('aberta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fechada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fechada_em')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};
