<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_setor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos_folha');
            $table->foreignId('setor_id')->constrained('setores');
            $table->boolean('ativo')->default(true);
            $table->unique(['evento_id', 'setor_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_setor');
    }
};
