<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('eventos_folha', 'tipo_evento')) {
            Schema::table('eventos_folha', function (Blueprint $table) {
                $table->string('tipo_evento')->nullable()->after('descricao')
                    ->comment('Define lógica especial: INSALUBRIDADE, PERICULOSIDADE, NOTURNO, GRATIFICACAO, TURNO');
            });
        }

        // Tentar popular automaticamente com base na descrição existente
        $eventos = DB::table('eventos_folha')->get();
        foreach ($eventos as $evento) {
            $tipo = null;
            $desc = strtoupper($evento->descricao);

            if (str_contains($desc, 'INSALUBRIDADE')) {
                $tipo = 'INSALUBRIDADE';
            } elseif (str_contains($desc, 'PERICULOSIDADE')) {
                $tipo = 'PERICULOSIDADE';
            } elseif (str_contains($desc, 'ADICIONAL NOTURNO')) {
                $tipo = 'NOTURNO';
            } elseif (str_contains($desc, 'GRATIFICACAO') || str_contains($desc, 'GRATIFICAÇÃO')) {
                $tipo = 'GRATIFICACAO';
            } elseif (str_contains($desc, 'TURNO')) {
                $tipo = 'TURNO';
            }

            if ($tipo) {
                DB::table('eventos_folha')
                    ->where('id', $evento->id)
                    ->update(['tipo_evento' => $tipo]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos_folha', function (Blueprint $table) {
            $table->dropColumn('tipo_evento');
        });
    }
};
