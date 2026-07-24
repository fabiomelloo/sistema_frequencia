<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->char('hash_anterior', 64)->nullable()->after('user_agent');
            $table->char('hash_registro', 64)->nullable()->after('hash_anterior');
        });

        Schema::create('audit_chain_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('ultimo_hash', 64)->nullable();
            $table->timestamps();
        });
        DB::table('audit_chain_state')->insert([
            'id' => 1,
            'ultimo_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hashAnterior = null;
        DB::table('audit_logs')->orderBy('id')->chunkById(250, function ($registros) use (&$hashAnterior): void {
            foreach ($registros as $registro) {
                $uuid = (string) Str::uuid();
                DB::table('audit_logs')->where('id', $registro->id)->update([
                    'uuid' => $uuid,
                    'hash_anterior' => $hashAnterior,
                ]);
                $atualizado = DB::table('audit_logs')->where('id', $registro->id)->first();
                $hashAnterior = $this->calcularHash($atualizado);
                DB::table('audit_logs')->where('id', $registro->id)->update([
                    'hash_registro' => $hashAnterior,
                ]);
            }
        });

        DB::table('audit_chain_state')->where('id', 1)->update([
            'ultimo_hash' => $hashAnterior,
            'updated_at' => now(),
        ]);

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->char('hash_registro', 64)->nullable(false)->change();
            $table->unique('uuid');
            $table->unique('hash_registro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_state');
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropUnique(['hash_registro']);
            $table->dropColumn(['uuid', 'hash_anterior', 'hash_registro']);
        });
    }

    private function calcularHash(object $registro): string
    {
        $campos = [
            'id', 'uuid', 'user_id', 'user_name', 'acao', 'modelo', 'modelo_id', 'descricao',
            'dados_antes', 'dados_depois', 'ip', 'user_agent', 'created_at', 'hash_anterior',
        ];
        $conteudo = [];
        foreach ($campos as $campo) {
            $conteudo[$campo] = $registro->{$campo};
        }

        return hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
};
