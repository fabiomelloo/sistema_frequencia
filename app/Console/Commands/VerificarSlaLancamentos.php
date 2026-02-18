<?php

namespace App\Console\Commands;

use App\Services\CompetenciaService;
use App\Services\NotificacaoService;
use Illuminate\Console\Command;

class VerificarSlaLancamentos extends Command
{
    protected $signature = 'sla:verificar';
    protected $description = 'Verifica lançamentos com SLA ultrapassado e notifica responsáveis';

    public function handle(CompetenciaService $competenciaService): int
    {
        $resultado = $competenciaService->verificarSla();

        if ($resultado['total_atrasados'] === 0) {
            $this->info('Nenhum lançamento com SLA ultrapassado.');
            return Command::SUCCESS;
        }

        // Agrupar atrasados por setor para notificar responsáveis
        $porSetor = $resultado['lancamentos']->groupBy('setor_origem_id');

        foreach ($porSetor as $setorId => $lancamentos) {
            $total = $lancamentos->count();
            $setorNome = $lancamentos->first()->setorOrigem->nome ?? 'Setor #' . $setorId;

            // Notificar usuários CENTRAL sobre atrasos
            $usuariosCentral = \App\Models\User::where('role', \App\Enums\UserRole::CENTRAL)->get();
            foreach ($usuariosCentral as $usuario) {
                NotificacaoService::criar(
                    $usuario->id,
                    'alerta',
                    'SLA Ultrapassado',
                    "{$total} lançamento(s) do setor '{$setorNome}' estão há mais de {$resultado['sla_dias']} dias aguardando conferência.",
                    route('painel.index', ['setor_id' => $setorId])
                );
            }
        }

        $this->info("{$resultado['total_atrasados']} lançamento(s) com SLA ultrapassado. Notificações enviadas.");

        return Command::SUCCESS;
    }
}
