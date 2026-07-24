<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Models\Configuracao;
use App\Models\LancamentoSetorial;
use App\Models\LotacaoHistorico;
use App\Models\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ServidorCicloVidaService
{
    /**
     * Processa transferência de servidor entre setores.
     *
     * Regras:
     * - Cria histórico de lotação do setor antigo
     * - Atualiza setor atual do servidor
     * - Notifica setor origem sobre lançamentos pendentes
     * - Opcionalmente transfere lançamentos pendentes para novo setor (configurável)
     *
     * @return array ['lancamentos_afetados' => int, 'acao' => string]
     */
    public function transferirServidor(
        Servidor $servidor,
        int $novoSetorId,
        Carbon $dataTransferencia,
        ?string $motivo = null
    ): array {
        DB::beginTransaction();
        try {
            $setorAntigoId = $servidor->setor_id;

            // Validar que não está transferindo para o mesmo setor
            if ($setorAntigoId === $novoSetorId) {
                throw new \InvalidArgumentException('O servidor já pertence a este setor.');
            }

            // 1. Buscar lançamentos pendentes do servidor
            $lancamentosPendentes = LancamentoSetorial::where('servidor_id', $servidor->id)
                ->whereIn('status', [
                    LancamentoStatus::PENDENTE,
                    LancamentoStatus::CONFERIDO_SETORIAL,
                    LancamentoStatus::REJEITADO,
                ])
                ->get();

            // 2. Criar histórico de lotação do setor antigo
            $dataFimLotacaoAntiga = $dataTransferencia->copy()->subDay();

            // Verificar se já existe lotação ativa para este setor
            $lotacaoAtiva = LotacaoHistorico::where('servidor_id', $servidor->id)
                ->where('setor_id', $setorAntigoId)
                ->whereNull('data_fim')
                ->first();

            if ($lotacaoAtiva) {
                // Fechar lotação existente
                $lotacaoAtiva->data_fim = $dataFimLotacaoAntiga;
                $lotacaoAtiva->observacao = ($lotacaoAtiva->observacao ?? '').
                    " | Transferência para setor {$novoSetorId} em {$dataTransferencia->format('d/m/Y')}. Motivo: {$motivo}";
                $lotacaoAtiva->save();
            } else {
                // Criar nova entrada de histórico
                $dataInicioLotacao = $servidor->data_admissao ?? now()->subYear();

                LotacaoHistorico::create([
                    'servidor_id' => $servidor->id,
                    'setor_id' => $setorAntigoId,
                    'data_inicio' => $dataInicioLotacao,
                    'data_fim' => $dataFimLotacaoAntiga,
                    'observacao' => "Transferência para setor {$novoSetorId} em {$dataTransferencia->format('d/m/Y')}. Motivo: {$motivo}",
                ]);
            }

            // 3. Criar histórico de lotação do novo setor
            LotacaoHistorico::create([
                'servidor_id' => $servidor->id,
                'setor_id' => $novoSetorId,
                'data_inicio' => $dataTransferencia,
                'data_fim' => null, // Lotação atual
                'observacao' => "Transferido do setor {$setorAntigoId}. Motivo: {$motivo}",
            ]);

            // 4. Atualizar setor do servidor
            $servidor->setor_id = $novoSetorId;
            Servidor::withoutEvents(fn () => $servidor->save());

            // 5. Opção: Transferir lançamentos pendentes para novo setor (configurável)
            $transferirLancamentos = Configuracao::get('transferir_lancamentos_ao_mudar_setor', 'false') === 'true';
            $lancamentosTransferidos = 0;

            if ($transferirLancamentos) {
                foreach ($lancamentosPendentes as $lancamento) {
                    $lancamento->setor_origem_id = $novoSetorId;
                    $lancamento->save();
                    $lancamentosTransferidos++;
                }
            }

            // 6. Notificar setor origem sobre pendências
            if ($lancamentosPendentes->isNotEmpty()) {
                $usuariosSetorOrigem = User::where('setor_id', $setorAntigoId)->get();

                foreach ($usuariosSetorOrigem as $usuario) {
                    NotificacaoService::criar(
                        $usuario->id,
                        'TRANSFERENCIA_SERVIDOR',
                        'Servidor Transferido',
                        "O servidor {$servidor->nome} foi transferido para outro setor. ".
                        ($transferirLancamentos
                            ? "{$lancamentosTransferidos} lançamento(s) pendente(s) foram transferidos."
                            : "Verifique os {$lancamentosPendentes->count()} lançamento(s) pendente(s) deste servidor."),
                        route('lancamentos.index', ['servidor_id' => $servidor->id])
                    );
                }
            }

            // 7. Registrar auditoria
            AuditService::registrar(
                'TRANSFERIU_SERVIDOR',
                'Servidor',
                $servidor->id,
                "Servidor transferido do setor {$setorAntigoId} para {$novoSetorId}. ".
                "Data: {$dataTransferencia->format('d/m/Y')}. ".
                "Lançamentos afetados: {$lancamentosPendentes->count()}. ".
                "Motivo: {$motivo}"
            );

            DB::commit();

            return [
                'lancamentos_afetados' => $lancamentosPendentes->count(),
                'lancamentos_transferidos' => $lancamentosTransferidos,
                'lancamentos_mantidos' => $lancamentosPendentes->count() - $lancamentosTransferidos,
                'acao' => $transferirLancamentos ? 'transferidos' : 'mantidos_no_setor_origem',
                'setor_origem_notificado' => $lancamentosPendentes->isNotEmpty(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Processa desligamento/exoneração/aposentadoria de servidor.
     *
     * Regras:
     * - Cancela automaticamente lançamentos pendentes de competências futuras ao desligamento
     * - Mantém lançamentos de competências já passadas (para histórico)
     * - Marca servidor como inativo
     * - Notifica setores afetados
     *
     * @param  string  $motivo  EXONERACAO, APOSENTADORIA, etc.
     * @return array ['lancamentos_cancelados' => int, 'setores_notificados' => int]
     */
    public function desligarServidor(
        Servidor $servidor,
        Carbon $dataDesligamento,
        string $motivo
    ): array {
        DB::beginTransaction();
        try {
            $competenciaDesligamento = $dataDesligamento->format('Y-m');
            $dataDesligamentoInicioMes = $dataDesligamento->copy()->startOfMonth();

            // Lançamentos pendentes de competências futuras ao desligamento
            $lancamentosCancelar = LancamentoSetorial::where('servidor_id', $servidor->id)
                ->whereIn('status', [
                    LancamentoStatus::PENDENTE,
                    LancamentoStatus::CONFERIDO_SETORIAL,
                    LancamentoStatus::REJEITADO,
                ])
                ->where('competencia', '>=', $competenciaDesligamento)
                ->get();

            $cancelados = $lancamentosCancelar->count();
            $motivoCancelamento = "Servidor desligado em {$dataDesligamento->format('d/m/Y')}. Motivo: {$motivo}";

            // Cancelamento em lote (evita N+1)
            if ($cancelados > 0) {
                LancamentoSetorial::whereIn('id', $lancamentosCancelar->pluck('id'))
                    ->update([
                        'status' => LancamentoStatus::CANCELADO->value,
                        'motivo_rejeicao' => $motivoCancelamento,
                        'id_validador' => auth()->id(),
                        'validated_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Atualizar servidor
            $servidor->ativo = false;
            $servidor->data_desligamento = $dataDesligamento;
            Servidor::withoutEvents(fn () => $servidor->save());

            $servidor->vinculosFuncionais()
                ->whereNull('data_fim')
                ->update(['data_fim' => $dataDesligamento, 'updated_at' => now()]);
            $servidor->lotacoes()
                ->whereNull('data_fim')
                ->update(['data_fim' => $dataDesligamento, 'updated_at' => now()]);

            // Notificar setores afetados
            $setoresAfetados = $lancamentosCancelar->pluck('setor_origem_id')->unique();
            $notificados = 0;

            foreach ($setoresAfetados as $setorId) {
                $usuarios = User::where('setor_id', $setorId)->get();

                foreach ($usuarios as $usuario) {
                    NotificacaoService::criar(
                        $usuario->id,
                        'SERVIDOR_DESLIGADO',
                        'Servidor Desligado',
                        "O servidor {$servidor->nome} foi desligado em {$dataDesligamento->format('d/m/Y')}. ".
                        "Motivo: {$motivo}. ".
                        "{$cancelados} lançamento(s) pendente(s) foram cancelados automaticamente.",
                        route('lancamentos.index', ['servidor_id' => $servidor->id])
                    );
                    $notificados++;
                }
            }

            // Registrar auditoria
            AuditService::registrar(
                'DESLIGOU_SERVIDOR',
                'Servidor',
                $servidor->id,
                "Servidor desligado. Motivo: {$motivo}. ".
                "Data: {$dataDesligamento->format('d/m/Y')}. ".
                "{$cancelados} lançamento(s) cancelado(s). ".
                "{$notificados} usuário(s) notificado(s)."
            );

            DB::commit();

            return [
                'lancamentos_cancelados' => $cancelados,
                'setores_notificados' => $setoresAfetados->count(),
                'usuarios_notificados' => $notificados,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function reativarServidor(Servidor $servidor, Carbon $dataReativacao, ?int $usuarioId): void
    {
        DB::transaction(function () use ($servidor, $dataReativacao, $usuarioId): void {
            if ($servidor->ativo) {
                throw new \InvalidArgumentException('O servidor já está ativo.');
            }
            if (! $servidor->cargo || ! $servidor->vinculo || ! $servidor->carga_horaria) {
                throw new \InvalidArgumentException('Complete os dados funcionais antes de reativar o servidor.');
            }
            if ($servidor->data_desligamento && $dataReativacao->lte($servidor->data_desligamento)) {
                throw new \InvalidArgumentException('A reativação deve ocorrer depois do desligamento.');
            }

            Servidor::withoutEvents(fn () => $servidor->update(['ativo' => true, 'data_desligamento' => null]));
            $servidor->vinculosFuncionais()->create([
                'matricula' => $servidor->matricula,
                'tipo_vinculo' => $servidor->vinculo,
                'cargo' => $servidor->cargo,
                'carga_horaria' => $servidor->carga_horaria,
                'data_inicio' => $dataReativacao,
                'observacao' => 'Nova vigência criada pela reativação do servidor.',
                'registrado_por_id' => $usuarioId,
            ]);
            $servidor->lotacoes()->create([
                'setor_id' => $servidor->setor_id,
                'data_inicio' => $dataReativacao,
                'observacao' => 'Nova lotação criada pela reativação do servidor.',
            ]);

            AuditService::registrar(
                'REATIVOU_SERVIDOR',
                'Servidor',
                $servidor->id,
                "Servidor reativado com nova vigência em {$dataReativacao->format('d/m/Y')}."
            );
        });
    }
}
