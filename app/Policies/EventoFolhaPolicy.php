<?php

namespace App\Policies;

use App\Models\EventoFolha;
use App\Models\User;

class EventoFolhaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function view(User $user, EventoFolha $eventoFolha): bool
    {
        return $user->isAdmin() || $user->isCentral();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EventoFolha $eventoFolha): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EventoFolha $eventoFolha): bool
    {
        return $user->isAdmin();
    }
}
