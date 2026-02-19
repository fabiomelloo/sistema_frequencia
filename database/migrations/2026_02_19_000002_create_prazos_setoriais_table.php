<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prazos_setoriais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')->constrained('competencias')->cascadeOnDelete();
            $table->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $table->date('data_limite');
            $table->timestamp('fechado_em')->nullable();
            $table->foreignId('fechado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['competencia_id', 'setor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prazos_setoriais');
    }
};
