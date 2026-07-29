<?php

namespace App\Http\Requests;

use App\Models\Delegacao;
use App\Models\EventoFolha;
use App\Models\Servidor;
use Illuminate\Contracts\Validation\Validator;

class UpdateLancamentoSetorialRequest extends StoreLancamentoSetorialRequest
{
    /**
     * Sobrescreve o withValidator para validações de permissão/acesso.
     * Regras de negócio completas são validadas pelo
     * RegrasLancamentoService no Controller, que passa o $lancamentoId.
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

            if (! $servidor || ! $evento) {
                return;
            }

            $user = auth()->user();
            $setorDoServidor = $servidor->setor_id;

            if ($setorDoServidor !== $user->setor_id) {
                if (! Delegacao::temDelegacaoAtiva($user->id, $setorDoServidor)) {
                    $validator->errors()->add('servidor_id', 'Servidor não pertence ao seu setor e você não possui delegação ativa para o setor dele.');

                    return;
                }
            }

            if (! $servidor->ativo) {
                $validator->errors()->add('servidor_id', 'Servidor está inativo e não pode receber lançamentos.');

                return;
            }

            if (! $evento->ativo) {
                $validator->errors()->add('evento_id', 'Este evento está inativo e não pode ser utilizado.');

                return;
            }

            if (! $evento->temDireitoNoSetor($setorDoServidor)) {
                $validator->errors()->add('evento_id', 'O setor do servidor não possui direito a este evento.');

                return;
            }
        });
    }
}
