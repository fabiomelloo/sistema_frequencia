<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUS_ATUAIS = [
        'PENDENTE',
        'CONFERIDO_SETORIAL',
        'CONFERIDO',
        'REJEITADO',
        'EXPORTADO',
        'ESTORNADO',
        'CANCELADO',
        'ESTORNO_SOLICITADO',
    ];

    private const STATUS_ANTERIORES = [
        'PENDENTE',
        'CONFERIDO_SETORIAL',
        'CONFERIDO',
        'REJEITADO',
        'EXPORTADO',
        'ESTORNADO',
    ];

    public function up(): void
    {
        $this->alterarStatus(self::STATUS_ATUAIS);
    }

    public function down(): void
    {
        $this->alterarStatus(self::STATUS_ANTERIORES);
    }

    private function alterarStatus(array $status): void
    {
        $driver = DB::getDriverName();
        $valores = implode("', '", $status);

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE lancamentos_setoriais MODIFY COLUMN status ENUM('{$valores}') DEFAULT 'PENDENTE'"
            );
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE lancamentos_setoriais DROP CONSTRAINT IF EXISTS lancamentos_setoriais_status_check');
            DB::statement(
                'ALTER TABLE lancamentos_setoriais ADD CONSTRAINT lancamentos_setoriais_status_check '.
                "CHECK (status::text = ANY (ARRAY['{$valores}']::text[]))"
            );
        }
    }
};
