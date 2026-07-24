<?php

namespace App\Observers;

use App\Enums\LancamentoStatus;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;

class ServidorObserver
{
    /**
     * Handle the Servidor "updated" event.
     *
     * Detecta mudanças de setor e de status (inativação) e cancela
     * automaticamente lançamentos pendentes afetados.
     *
     * Nota: o ServidorCicloVidaService salva o servidor sem disparar eventos
     * para evitar duplicação desta regra com o fluxo transacional completo.
     */
    public function updated(Servidor $servidor): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        // Mudança de setor (transferência manual via CRUD)
        if ($servidor->wasChanged('setor_id')) {
            $setorAntigoId = $servidor->getOriginal('setor_id');
            $this->cancelarLancamentosPendentes(
                $servidor->id,
                'Cancelado automaticamente: O servidor foi transferido de setor.',
                $setorAntigoId
            );
        }

        // Servidor desligado/inativado
        if ($servidor->wasChanged('ativo') && ! $servidor->ativo) {
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
     * @param  int|null  $setorOrigemId  Se informado, filtra apenas pelo setor antigo (transferência)
     * @param  string|null  $competenciaMinima  Se informado, filtra competência >= (desligamento)
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
            'status' => LancamentoStatus::CANCELADO->value,
            'motivo_rejeicao' => $motivo,
            'id_validador' => auth()->id(), // null em contexto de console — ação automática do sistema
            'validated_at' => now(),
        ]);
    }
}
