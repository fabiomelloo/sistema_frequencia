<?php

namespace App\Services;

use App\Models\OcorrenciaFrequencia;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OcorrenciaFrequenciaService
{
    public function criar(
        array $dados,
        User $user,
        string $origem = 'MANUAL',
        ?int $importacaoLinhaId = null,
    ): OcorrenciaFrequencia {
        return DB::transaction(function () use ($dados, $user, $origem, $importacaoLinhaId): OcorrenciaFrequencia {
            $atributos = $this->atributos($dados, $user);
            $atributos['origem'] = $origem;
            $atributos['importacao_linha_id'] = $importacaoLinhaId;
            $ocorrencia = OcorrenciaFrequencia::create($atributos);
            $this->sincronizarDias($ocorrencia, $dados['dias_especificos'] ?? []);

            return $ocorrencia;
        });
    }

    public function atualizar(OcorrenciaFrequencia $ocorrencia, array $dados, User $user): OcorrenciaFrequencia
    {
        return DB::transaction(function () use ($ocorrencia, $dados, $user): OcorrenciaFrequencia {
            $ocorrencia->update($this->atributos($dados, $user, false));
            $ocorrencia->dias()->delete();
            $this->sincronizarDias($ocorrencia, $dados['dias_especificos'] ?? []);

            return $ocorrencia->refresh();
        });
    }

    private function atributos(array $dados, User $user, bool $criacao = true): array
    {
        $atributos = Arr::only($dados, [
            'servidor_id', 'competencia_id', 'tipo', 'data_inicio', 'data_fim',
            'justificada', 'possui_comprovacao', 'referencia_documento', 'observacao_original',
        ]);

        $atributos['setor_id'] = $user->setor_id;

        if ($criacao) {
            $atributos['criado_por_id'] = $user->id;
        }

        return $atributos;
    }

    private function sincronizarDias(OcorrenciaFrequencia $ocorrencia, array $dias): void
    {
        foreach (array_unique($dias) as $dia) {
            $ocorrencia->dias()->create(['data' => $dia]);
        }
    }
}
