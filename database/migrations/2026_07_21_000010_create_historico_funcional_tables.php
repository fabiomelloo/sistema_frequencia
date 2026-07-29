<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculos_funcionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('matricula', 50);
            $table->string('tipo_vinculo', 30)->nullable();
            $table->string('cargo', 150)->nullable();
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->string('ato_referencia', 255)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'data_inicio', 'data_fim'], 'vinculo_servidor_vigencia_idx');
        });

        Schema::create('designacoes_funcionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('nivel', 30)->nullable();
            $table->string('descricao', 150);
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('ato_referencia', 255)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'tipo', 'data_inicio', 'data_fim'], 'designacao_servidor_vigencia_idx');
        });

        Schema::create('vantagens_funcionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('evento_id')->constrained('eventos_folha');
            $table->decimal('percentual', 7, 2)->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->decimal('quantidade', 8, 2)->nullable();
            $table->string('nivel', 30)->nullable();
            $table->text('texto')->nullable();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('referencia_documento', 255)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'evento_id', 'data_inicio', 'data_fim'], 'vantagem_servidor_vigencia_idx');
        });

        DB::table('servidores')->orderBy('id')->each(function (object $servidor): void {
            DB::table('vinculos_funcionais')->insert([
                'servidor_id' => $servidor->id,
                'matricula' => $servidor->matricula,
                'tipo_vinculo' => $servidor->vinculo,
                'cargo' => $servidor->cargo,
                'carga_horaria' => $servidor->carga_horaria,
                'data_inicio' => $servidor->data_admissao,
                'data_fim' => $servidor->data_desligamento,
                'observacao' => 'Registro inicial gerado a partir do cadastro anterior à implantação do histórico funcional.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vantagens_funcionais');
        Schema::dropIfExists('designacoes_funcionais');
        Schema::dropIfExists('vinculos_funcionais');
    }
};
