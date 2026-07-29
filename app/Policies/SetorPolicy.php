<?php

namespace App\Policies;

use App\Models\Setor;
use App\Models\User;

class SetorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function view(User $user, Setor $setor): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Setor $setor): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Setor $setor): bool
    {
        return $user->isAdmin();
    }
}
