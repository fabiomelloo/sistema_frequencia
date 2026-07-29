<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('role')->index();
            $table->unsignedSmallInteger('tentativas_login_falhas')->default(0)->after('ativo');
            $table->timestamp('bloqueado_ate')->nullable()->after('tentativas_login_falhas')->index();
            $table->timestamp('ultimo_login_em')->nullable()->after('bloqueado_ate');
            $table->timestamp('desativado_em')->nullable()->after('ultimo_login_em');
            $table->foreignId('desativado_por_id')->nullable()->after('desativado_em')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desativado_por_id');
            $table->dropIndex(['ativo']);
            $table->dropIndex(['bloqueado_ate']);
            $table->dropColumn([
                'ativo',
                'tentativas_login_falhas',
                'bloqueado_ate',
                'ultimo_login_em',
                'desativado_em',
            ]);
        });
    }
};
