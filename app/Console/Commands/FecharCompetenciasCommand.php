<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\PrazoSetorial;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CompetenciaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FecharCompetenciasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'frequencia:fechar-competencias';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica e fecha compentências e prazos setoriais que atingiram a data limite';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando verificação de prazos expirados...');

        // 1. Fechar Prazos Setoriais expirados
        $this->fecharPrazosSetoriais();

        // 2. Fechar Competências expiradas
        $this->fecharCompetenciasGerais();

        $this->info('Verificação concluída.');
    }

    private function fecharPrazosSetoriais(): void
    {
        $hoje = Carbon::today();

        $prazosExpirados = PrazoSetorial::whereNull('fechado_em')
            ->whereDate('data_limite', '<', $hoje)
            ->get();

        if ($prazosExpirados->isEmpty()) {
            $this->info('Nenhum prazo setorial expirado.');

            return;
        }

        $emailSistema = Configuracao::get('email_usuario_sistema', 'admin@example.com');
        $usuarioSistema = User::firstWhere('email', $emailSistema)
            ?: User::where('role', UserRole::ADMIN)->first()
            ?: User::first();

        DB::beginTransaction();
        try {
            foreach ($prazosExpirados as $prazo) {
                $prazo->fechado_em = now();
                $prazo->fechado_por = $usuarioSistema?->id;
                $prazo->save();

                AuditService::registrar(
                    'FECHOU_PRAZO',
                    'PrazoSetorial',
                    $prazo->id,
                    "Prazo do setor {$prazo->setor_id} para competência {$prazo->competencia->referencia} fechado automaticamente (data limite: {$prazo->data_limite->format('d/m/Y')})."
                );
            }
            DB::commit();
            $this->info("{$prazosExpirados->count()} prazos setoriais fechados automaticamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Erro ao fechar prazos setoriais: '.$e->getMessage());
        }
    }

    private function fecharCompetenciasGerais(): void
    {
        $hoje = Carbon::today();

        $competenciasExpiradas = Competencia::aberta()
            ->whereDate('data_limite', '<', $hoje)
            ->get();

        if ($competenciasExpiradas->isEmpty()) {
            $this->info('Nenhuma competência geral expirada.');

            return;
        }

        $service = app(CompetenciaService::class);
        $fechadasCount = 0;

        foreach ($competenciasExpiradas as $competencia) {
            try {
                $service->fechar($competencia);

                AuditService::registrar(
                    'FECHOU_COMPETENCIA',
                    'Competencia',
                    $competencia->id,
                    "Competência {$competencia->referencia} fechada automaticamente pelo sistema (data limite: {$competencia->data_limite->format('d/m/Y')})."
                );
                $fechadasCount++;
            } catch (\InvalidArgumentException $e) {
                $this->warn("Aviso: Não foi possível fechar automaticamente a competência {$competencia->referencia} (ID {$competencia->id}): ".$e->getMessage());
            } catch (\Exception $e) {
                $this->error("Erro inesperado ao fechar competência {$competencia->referencia}: ".$e->getMessage());
            }
        }

        $this->info("{$fechadasCount} competência(s) fechada(s) automaticamente.");
    }
}
