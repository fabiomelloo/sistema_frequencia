<?php

namespace App\Policies;

use App\Models\FolhaFrequencia;
use App\Models\User;

class FolhaFrequenciaPolicy
{
    public function view(User $user, FolhaFrequencia $folha): bool
    {
        return $user->role->podeFazerLancamentos() && $user->setor_id === $folha->setor_id;
    }

    public function update(User $user, FolhaFrequencia $folha): bool
    {
        return $this->view($user, $folha)
            && $folha->editavelPeloSetor()
            && $folha->competencia->estaAberta();
    }

    public function finalizar(User $user, FolhaFrequencia $folha): bool
    {
        return $this->update($user, $folha);
    }

    public function reabrir(User $user, FolhaFrequencia $folha): bool
    {
        return $this->view($user, $folha)
            && $folha->estaFinalizada()
            && $folha->competencia->estaAberta();
    }

    public function conferir(User $user, FolhaFrequencia $folha): bool
    {
        return $user->role->temAcessoPainel();
    }

    public function aprovar(User $user, FolhaFrequencia $folha): bool
    {
        return $this->conferir($user, $folha) && $folha->estaFinalizada();
    }

    public function devolver(User $user, FolhaFrequencia $folha): bool
    {
        return $this->aprovar($user, $folha) && $folha->competencia->estaAberta();
    }
}
