<?php

namespace App\Services;

use App\Enums\TipoOcorrenciaFrequencia;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OcorrenciaFrequenciaService
{
    public function criar(
        array $dados,
        User $user,
        string $origem = 'MANUAL',
        ?int $importacaoLinhaId = null,
    ): OcorrenciaFrequencia {
        return DB::transaction(function () use ($dados, $user, $origem, $importacaoLinhaId): OcorrenciaFrequencia {
            $this->bloquearServidores([$dados['servidor_id']]);
            $this->validarSobreposicao($dados);

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
            $ocorrencia = OcorrenciaFrequencia::query()->lockForUpdate()->findOrFail($ocorrencia->id);
            $this->bloquearServidores([$ocorrencia->servidor_id, $dados['servidor_id']]);
            $this->validarSobreposicao($dados, $ocorrencia->id);

            $ocorrencia->update($this->atributos($dados, $user, false));
            $ocorrencia->dias()->delete();
            $this->sincronizarDias($ocorrencia, $dados['dias_especificos'] ?? []);

            return $ocorrencia->refresh();
        });
    }

    /** @param array<int, int> $servidoresIds */
    private function bloquearServidores(array $servidoresIds): void
    {
        Servidor::query()
            ->whereKey(array_values(array_unique($servidoresIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function validarSobreposicao(array $dados, ?int $ignorarOcorrenciaId = null): void
    {
        $tipo = TipoOcorrenciaFrequencia::from($dados['tipo']);
        $datas = $this->datasEfetivas($dados);
        $datasIndexadas = array_fill_keys($datas, true);

        $existentes = OcorrenciaFrequencia::query()
            ->where('servidor_id', $dados['servidor_id'])
            ->when($ignorarOcorrenciaId, fn ($query) => $query->whereKeyNot($ignorarOcorrenciaId))
            ->with('dias')
            ->get();

        foreach ($existentes as $existente) {
            if ($tipo->podeCoexistirCom($existente->tipo)) {
                continue;
            }

            $conflitos = array_values(array_filter(
                $this->datasEfetivas([
                    'data_inicio' => $existente->data_inicio?->toDateString(),
                    'data_fim' => $existente->data_fim?->toDateString(),
                    'dias_especificos' => $existente->dias->pluck('data')->map->toDateString()->all(),
                ]),
                fn (string $data): bool => isset($datasIndexadas[$data]),
            ));

            if ($conflitos === []) {
                continue;
            }

            $datasFormatadas = collect(array_slice($conflitos, 0, 5))
                ->map(fn (string $data): string => Carbon::parse($data)->format('d/m/Y'))
                ->join(', ');
            $sufixo = count($conflitos) > 5 ? ' e outras datas' : '';
            $campo = empty($dados['data_inicio']) ? 'dias_especificos' : 'data_inicio';

            throw ValidationException::withMessages([
                $campo => "Conflito com {$existente->tipo->label()} já registrado em {$datasFormatadas}{$sufixo}.",
            ]);
        }
    }

    /** @return array<int, string> */
    private function datasEfetivas(array $dados): array
    {
        $datas = [];

        if (! empty($dados['data_inicio']) && ! empty($dados['data_fim'])) {
            foreach (CarbonPeriod::create($dados['data_inicio'], $dados['data_fim']) as $data) {
                $datas[$data->toDateString()] = true;
            }
        }

        foreach ($dados['dias_especificos'] ?? [] as $data) {
            $datas[Carbon::parse($data)->toDateString()] = true;
        }

        ksort($datas);

        return array_keys($datas);
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
