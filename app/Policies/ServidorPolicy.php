<?php

namespace App\Policies;

use App\Models\Servidor;
use App\Models\User;

class ServidorPolicy
{
    /**
     * CENTRAL e ADMIN podem visualizar servidores.
     */
    public function viewAny(User $user): bool
    {
        return $user->isCentral() || $user->isAdmin();
    }

    public function view(User $user, Servidor $servidor): bool
    {
        return $user->isCentral() || $user->isAdmin();
    }

    /**
     * Apenas CENTRAL pode criar/editar servidores.
     */
    public function create(User $user): bool
    {
        return $user->isCentral() || $user->isAdmin();
    }

    public function update(User $user, Servidor $servidor): bool
    {
        return $user->isCentral() || $user->isAdmin();
    }

    /**
     * Desativar: CENTRAL ou ADMIN; não pode desativar servidor sem lançamentos se já ativo.
     */
    public function delete(User $user, Servidor $servidor): bool
    {
        return $user->isCentral() || $user->isAdmin();
    }
}
