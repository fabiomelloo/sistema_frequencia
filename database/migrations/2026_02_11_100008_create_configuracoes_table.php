<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 100)->unique();
            $table->text('valor')->nullable();
            $table->string('descricao')->nullable();
            $table->timestamps();
        });

        // Valores padrão para SLA
        DB::table('configuracoes')->insert([
            ['chave' => 'sla_dias_conferencia', 'valor' => '5', 'descricao' => 'Prazo em dias para conferir lançamentos pendentes', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'sla_dias_alerta', 'valor' => '3', 'descricao' => 'Dias antes do prazo para enviar alerta', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
