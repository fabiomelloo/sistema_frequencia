<?php

namespace App\Policies;

use App\Models\EvidenciaDocumental;
use App\Models\FolhaFrequencia;
use App\Models\User;

class EvidenciaDocumentalPolicy
{
    public function view(User $user, EvidenciaDocumental $evidencia): bool
    {
        if ($user->role->temAcessoPainel()) {
            return true;
        }

        if (! $user->role->podeFazerLancamentos()) {
            return false;
        }

        return $this->setorId($evidencia) === $user->setor_id;
    }

    public function delete(User $user, EvidenciaDocumental $evidencia): bool
    {
        if (! $this->view($user, $evidencia) || ! $user->role->podeFazerLancamentos()) {
            return false;
        }

        if ($evidencia->folha_frequencia_item_id) {
            $folha = $evidencia->itemFrequencia->servidorFolha->folha;

            return $folha->editavelPeloSetor() && $folha->competencia->estaAberta();
        }

        $ocorrencia = $evidencia->ocorrencia;
        if (! $ocorrencia->competencia->estaAberta()) {
            return false;
        }

        $folha = FolhaFrequencia::query()
            ->where('setor_id', $ocorrencia->setor_id)
            ->where('competencia_id', $ocorrencia->competencia_id)
            ->first();

        return ! $folha || $folha->editavelPeloSetor();
    }

    private function setorId(EvidenciaDocumental $evidencia): ?int
    {
        if ($evidencia->ocorrencia_frequencia_id) {
            return $evidencia->ocorrencia->setor_id;
        }

        return $evidencia->itemFrequencia->servidorFolha->folha->setor_id;
    }
}
