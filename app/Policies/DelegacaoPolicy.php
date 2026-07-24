<?php

namespace App\Policies;

use App\Models\Delegacao;
use App\Models\User;

class DelegacaoPolicy
{
    /**
     * Determina se o usuário pode ver a lista de delegações (qualquer pessoa do Setorial).
     */
    public function viewAny(User $user): bool
    {
        return $user->isSetorial();
    }

    /**
     * Determina se o usuário pode criar uma delegação.
     */
    public function create(User $user): bool
    {
        return $user->isSetorial();
    }

    /**
     * Determina se o usuário pode revogar uma delegação (apenas o delegante).
     */
    public function delete(User $user, Delegacao $delegacao): bool
    {
        return $user->id === $delegacao->delegante_id;
    }
}
