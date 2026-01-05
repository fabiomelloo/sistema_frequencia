<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos_setoriais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('evento_id')->constrained('eventos_folha');
            $table->foreignId('setor_origem_id')->constrained('setores');
            $table->integer('dias_lancados')->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->enum('status', ['PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO'])->default('PENDENTE');
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('id_validador')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('exportado_em')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos_setoriais');
    }
};
