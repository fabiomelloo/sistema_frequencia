<?php

namespace App\Policies;

use App\Models\Competencia;
use App\Models\User;

class CompetenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function view(User $user, Competencia $competencia): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    /**
     * Apenas ADMIN ou CENTRAL podem criar/fechar/reabrir competências.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function update(User $user, Competencia $competencia): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function delete(User $user, Competencia $competencia): bool
    {
        return $user->isAdmin();
    }
}
