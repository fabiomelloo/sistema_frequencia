<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_folha', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_evento');
            $table->string('descricao');
            $table->boolean('exige_dias')->default(false);
            $table->boolean('exige_valor')->default(false);
            $table->decimal('valor_minimo', 10, 2)->nullable();
            $table->decimal('valor_maximo', 10, 2)->nullable();
            $table->integer('dias_maximo')->nullable();
            $table->boolean('exige_observacao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_folha');
    }
};
