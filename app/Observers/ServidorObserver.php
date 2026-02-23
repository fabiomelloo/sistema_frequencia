<?php

namespace App\Observers;

use App\Models\Servidor;
use App\Services\ServidorCicloVidaService;

class ServidorObserver
{
    /**
     * Handle the Servidor "updated" event.
     */
    public function updated(Servidor $servidor): void
    {
        // Verifica se o setor mudou
        if ($servidor->wasChanged('setor_id')) {
            $setorAntigoId = $servidor->getOriginal('setor_id');
            $novoSetorId = $servidor->setor_id;
            
            // Reverte a alteração temporariamente para o service lidar com isso e salvar o histórico
            // O service já chama $servidor->save(), então vamos evitar um loop. 
            // Para não ter problema, podemos apenas informar o antigo e o novo pro service em um método específico 
            // ou rodar a lógica aqui com cuidado.
            // Para reaproveitar o ServidorCicloVidaService que faz a transação e tudo,
            // podemos alterar o service ou apenas fazer o tratamento direto aqui (não muito ideal pois duplicaria código).
            // A melhor abordagem com o model já salvo é que o Service `transferirServidor` parece
            // feito pra ser chamado de um Controller (onde o Model não foi salvo ainda).
            
            // Vamos delegar para o service se não for uma atualização gerada de forma automática sem request de transferência explícito
            // Vamos checar se essa atualização já não foi feita dentro do próprio service.
            // Se foi feita pelo service, não precisamos rodar nada aqui, pois o service já roda as notificações e lógicas.
            // Para evitar a duplicação se a modificação veio do admin:
            if (!app()->runningInConsole() && !app()->bound('servidor.transferindo')) {
                // Aqui seria a implementação do Observer para detectar mudanças manuais via CRUD ou Nova.
                // Como não sabemos a raiz de todas as mudanças, podemos apenas cancelar as pendências antigas:
                $servidorId = $servidor->id;
                $lancamentosCancelar = \App\Models\LancamentoSetorial::where('servidor_id', $servidorId)
                    ->where('setor_origem_id', $setorAntigoId)
                    ->whereIn('status', [
                        \App\Enums\LancamentoStatus::PENDENTE,
                        \App\Enums\LancamentoStatus::CONFERIDO_SETORIAL,
                        \App\Enums\LancamentoStatus::REJEITADO,
                    ])->get();

                foreach ($lancamentosCancelar as $lancamento) {
                    $lancamento->status = \App\Enums\LancamentoStatus::REJEITADO;
                    $lancamento->motivo_rejeicao = "Cancelado automaticamente: O servidor foi transferido de setor.";
                    $lancamento->id_validador = auth()->id() ?? 1; // Fallback se via comando
                    $lancamento->validated_at = now();
                    $lancamento->save();
                }
            }
        }

        // Verifica se o servidor foi desligado/inativado
        if ($servidor->wasChanged('ativo') && !$servidor->ativo) {
            $dataDesligamento = $servidor->data_desligamento ?? now();
            
            // Só cancela lançamentos futuros ao desligamento.
            $competenciaDesligamento = $dataDesligamento->format('Y-m');

            $lancamentosCancelar = \App\Models\LancamentoSetorial::where('servidor_id', $servidor->id)
                ->whereIn('status', [
                    \App\Enums\LancamentoStatus::PENDENTE,
                    \App\Enums\LancamentoStatus::CONFERIDO_SETORIAL,
                    \App\Enums\LancamentoStatus::REJEITADO,
                ])
                ->where('competencia', '>=', $competenciaDesligamento)
                ->get();

            foreach ($lancamentosCancelar as $lancamento) {
                $lancamento->status = \App\Enums\LancamentoStatus::REJEITADO;
                $lancamento->motivo_rejeicao = "Cancelado automaticamente: O servidor foi desligado/exonerado na competência " . $competenciaDesligamento . ".";
                $lancamento->id_validador = auth()->id() ?? 1;
                $lancamento->validated_at = now();
                $lancamento->save();
            }
        }
    }
}
