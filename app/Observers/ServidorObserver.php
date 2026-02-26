<?php

namespace App\Observers;

use App\Models\Servidor;
use App\Models\LancamentoSetorial;
use App\Enums\LancamentoStatus;

class ServidorObserver
{
    /**
     * Handle the Servidor "updated" event.
     *
     * Detecta mudanças de setor e de status (inativação) e cancela
     * automaticamente lançamentos pendentes afetados.
     *
     * Nota: Quando a mudança é feita via ServidorCicloVidaService
     * (que faz bind 'servidor.transferindo'), este observer não age
     * para evitar duplicação de lógica.
     */
    public function updated(Servidor $servidor): void
    {
        // Se foi acionado pelo ServidorCicloVidaService, não agir novamente
        if (app()->runningInConsole() || app()->bound('servidor.transferindo')) {
            return;
        }

        // Mudança de setor (transferência manual via CRUD)
        if ($servidor->wasChanged('setor_id')) {
            $setorAntigoId = $servidor->getOriginal('setor_id');
            $this->cancelarLancamentosPendentes(
                $servidor->id,
                "Cancelado automaticamente: O servidor foi transferido de setor.",
                $setorAntigoId
            );
        }

        // Servidor desligado/inativado
        if ($servidor->wasChanged('ativo') && !$servidor->ativo) {
            $dataDesligamento = $servidor->data_desligamento ?? now();
            $competenciaDesligamento = $dataDesligamento->format('Y-m');
            $this->cancelarLancamentosPendentes(
                $servidor->id,
                "Cancelado automaticamente: O servidor foi desligado/exonerado na competência {$competenciaDesligamento}.",
                null,
                $competenciaDesligamento
            );
        }
    }

    /**
     * Cancela lançamentos pendentes de um servidor.
     *
     * @param int $servidorId
     * @param string $motivo
     * @param int|null $setorOrigemId  Se informado, filtra apenas pelo setor antigo (transferência)
     * @param string|null $competenciaMinima  Se informado, filtra competência >= (desligamento)
     */
    private function cancelarLancamentosPendentes(
        int $servidorId,
        string $motivo,
        ?int $setorOrigemId = null,
        ?string $competenciaMinima = null
    ): void {
        $query = LancamentoSetorial::where('servidor_id', $servidorId)
            ->whereIn('status', [
                LancamentoStatus::PENDENTE,
                LancamentoStatus::CONFERIDO_SETORIAL,
                LancamentoStatus::REJEITADO,
            ]);

        if ($setorOrigemId) {
            $query->where('setor_origem_id', $setorOrigemId);
        }

        if ($competenciaMinima) {
            $query->where('competencia', '>=', $competenciaMinima);
        }

        $query->update([
            'status' => LancamentoStatus::REJEITADO->value,
            'motivo_rejeicao' => $motivo,
            'id_validador' => auth()->id() ?? 1,
            'validated_at' => now(),
        ]);
    }
}
