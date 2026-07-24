<?php

namespace App\Console\Commands;

use App\Services\AuditIntegrityService;
use Illuminate\Console\Command;

class VerificarIntegridadeAuditoria extends Command
{
    protected $signature = 'auditoria:verificar-integridade';

    protected $description = 'Verifica o encadeamento criptográfico dos registros de auditoria';

    public function handle(AuditIntegrityService $service): int
    {
        $resultado = $service->verificar();

        if (! $resultado['ok']) {
            $this->error('Falha de integridade na auditoria: '.$resultado['motivo']);
            if ($resultado['registro_invalido']) {
                $this->line('Primeiro registro inválido: #'.$resultado['registro_invalido']);
            }

            return self::FAILURE;
        }

        $this->info("Cadeia de auditoria íntegra: {$resultado['total']} registro(s) verificado(s).");

        return self::SUCCESS;
    }
}
