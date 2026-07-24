<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competencias', function (Blueprint $table) {
            $table->date('data_inicio')->nullable()->after('referencia');
            $table->date('data_fim')->nullable()->after('data_inicio');
            $table->index(['data_inicio', 'data_fim'], 'competencias_periodo_index');
        });

        DB::table('competencias')
            ->select(['id', 'referencia'])
            ->orderBy('id')
            ->eachById(function (object $competencia): void {
                $base = DateTimeImmutable::createFromFormat('!Y-m', $competencia->referencia);

                if ($base === false) {
                    return;
                }

                DB::table('competencias')
                    ->where('id', $competencia->id)
                    ->update([
                        'data_inicio' => $base->modify('-1 month')->modify('+10 days')->format('Y-m-d'),
                        'data_fim' => $base->modify('+9 days')->format('Y-m-d'),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('competencias', function (Blueprint $table) {
            $table->dropIndex('competencias_periodo_index');
            $table->dropColumn(['data_inicio', 'data_fim']);
        });
    }
};
