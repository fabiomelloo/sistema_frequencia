<?php

namespace App\Policies;

use App\Models\Delegacao;
use App\Models\LancamentoSetorial;
use App\Models\User;

class LancamentoSetorialPolicy
{
    /**
     * Visualizar: mesmo setor ou delegação ativa.
     */
    public function view(User $user, LancamentoSetorial $lancamento): bool
    {
        return $lancamento->setor_origem_id === $user->setor_id
            || Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);
    }

    /**
     * Editar / Atualizar: mesmo setor ou delegação ativa E lançamento editável.
     */
    public function update(User $user, LancamentoSetorial $lancamento): bool
    {
        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        return $temAcesso && $lancamento->podeSerEditado();
    }

    /**
     * Excluir (soft-delete): mesmo critério que editar.
     */
    public function delete(User $user, LancamentoSetorial $lancamento): bool
    {
        return $this->update($user, $lancamento);
    }

    /**
     * Cancelar: mesmo setor ou delegação E status cancelável.
     */
    public function cancelar(User $user, LancamentoSetorial $lancamento): bool
    {
        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        return $temAcesso && $lancamento->podeSerCancelado();
    }

    /**
     * Solicitar estorno: mesmo setor ou delegação E status permite estorno.
     */
    public function solicitarEstorno(User $user, LancamentoSetorial $lancamento): bool
    {
        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        return $temAcesso && $lancamento->podeSolicitarEstorno();
    }

    /**
     * Aprovar setorialmente: apenas o setor de origem, e o usuário NÃO pode ser o criador.
     * Usa criado_por_id diretamente (fonte de verdade deterministica, não depende do AuditLog).
     */
    public function aprovarSetorial(User $user, LancamentoSetorial $lancamento): bool
    {
        if ($lancamento->setor_origem_id !== $user->setor_id) {
            return false;
        }

        return $lancamento->criado_por_id !== $user->id;
    }
}
