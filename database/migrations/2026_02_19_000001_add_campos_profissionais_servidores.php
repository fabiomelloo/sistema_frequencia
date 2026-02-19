<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('cargo', 150)->nullable()->after('nome');
            $table->string('vinculo', 30)->nullable()->after('cargo')
                ->comment('EFETIVO, COMISSIONADO, TEMPORARIO, CONTRATADO');
            $table->unsignedSmallInteger('carga_horaria')->nullable()->after('vinculo')
                ->comment('Carga horária semanal: 20, 30, 40');
            $table->string('cpf', 14)->nullable()->unique()->after('matricula');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'vinculo', 'carga_horaria', 'cpf']);
        });
    }
};
