<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Apenas ADMIN e CENTRAL podem listar/visualizar usuários.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    /**
     * Apenas ADMIN pode criar/editar/deletar usuários.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        // ADMIN pode editar qualquer um; usuário pode editar a si mesmo somente via PerfilController
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Não pode deletar a si mesmo
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
