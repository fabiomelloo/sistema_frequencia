<?php

namespace App\Policies;

use App\Models\FolhaFrequencia;
use App\Models\OcorrenciaFrequencia;
use App\Models\User;

class OcorrenciaFrequenciaPolicy
{
    public function view(User $user, OcorrenciaFrequencia $ocorrencia): bool
    {
        return $ocorrencia->setor_id === $user->setor_id;
    }

    public function update(User $user, OcorrenciaFrequencia $ocorrencia): bool
    {
        if (! $this->view($user, $ocorrencia) || ! $ocorrencia->competencia->estaAberta()) {
            return false;
        }

        $folha = FolhaFrequencia::query()
            ->where('setor_id', $ocorrencia->setor_id)
            ->where('competencia_id', $ocorrencia->competencia_id)
            ->first();

        return ! $folha || $folha->editavelPeloSetor();
    }

    public function delete(User $user, OcorrenciaFrequencia $ocorrencia): bool
    {
        return $this->update($user, $ocorrencia);
    }
}
