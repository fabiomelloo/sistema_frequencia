<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use App\Models\Servidor;
use App\Models\EventoFolha;
use InvalidArgumentException;

class UpdateLancamentoSetorialRequest extends StoreLancamentoSetorialRequest
{
    /**
     * Sobrescreve o withValidator para passar o ID do lançamento sendo editado,
     * evitando falsa duplicata na validação.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->fails()) {
                return;
            }

            $data = $this->all();

            $servidor = Servidor::find($data['servidor_id'] ?? null);
            $evento = EventoFolha::find($data['evento_id'] ?? null);

            if (!$servidor || !$evento) {
                return;
            }

            $user = auth()->user();
            $setorDoServidor = $servidor->setor_id;

            // Verifica se o servidor pertence ao setor do usuário OU se há delegação ativa
            if ($setorDoServidor !== $user->setor_id) {
                if (!\App\Models\Delegacao::temDelegacaoAtiva($user->id, $setorDoServidor)) {
                    $validator->errors()->add('servidor_id', 'Servidor não pertence ao seu setor e você não possui delegação ativa para o setor dele.');
                    return;
                }
            }

            if (!$servidor->ativo) {
                $validator->errors()->add('servidor_id', 'Servidor está inativo e não pode receber lançamentos.');
                return;
            }

            if (!$evento->ativo) {
                $validator->errors()->add('evento_id', 'Este evento está inativo e não pode ser utilizado.');
                return;
            }

            if (!$evento->temDireitoNoSetor($setorDoServidor)) {
                $validator->errors()->add('evento_id', 'O setor do servidor não possui direito a este evento.');
                return;
            }

            // Passa o ID do lançamento sendo editado para que existeDuplicata o ignore
            $lancamentoId = $this->route('lancamento')?->id ?? $this->route('lancamento');

            try {
                app(\App\Services\RegrasLancamentoService::class)->validar(
                    $servidor,
                    $evento,
                    [
                        'competencia'                => $data['competencia'] ?? null,
                        'dias_trabalhados'           => $data['dias_trabalhados'] ?? null,
                        'dias_noturnos'              => $data['dias_noturnos'] ?? null,
                        'valor'                      => $data['valor'] ?? null,
                        'valor_gratificacao'         => $data['valor_gratificacao'] ?? null,
                        'porcentagem'                => $data['porcentagem'] ?? null,
                        'adicional_turno'            => $data['adicional_turno'] ?? null,
                        'adicional_noturno'          => $data['adicional_noturno'] ?? null,
                        'porcentagem_insalubridade'  => $data['porcentagem_insalubridade'] ?? null,
                        'porcentagem_periculosidade' => $data['porcentagem_periculosidade'] ?? null,
                        'observacao'                 => $data['observacao'] ?? null,
                    ],
                    is_numeric($lancamentoId) ? (int) $lancamentoId : null
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('regra_negocio', $e->getMessage());
            }
        });
    }
}
