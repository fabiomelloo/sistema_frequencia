<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\Servidor;
use App\Models\EventoFolha;
use InvalidArgumentException;

class StoreLancamentoSetorialRequest extends FormRequest
{

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isSetorial();
    }

    public function rules(): array
    {
        return [
            'competencia' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'servidor_id' => ['required', 'exists:servidores,id'],
            'evento_id' => ['required', 'exists:eventos_folha,id'],
            'dias_trabalhados' => ['nullable', 'integer', 'min:0'],
            'dias_noturnos' => ['nullable', 'integer', 'min:0'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'valor_gratificacao' => ['nullable', 'numeric', 'min:0'],
            'porcentagem' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'adicional_turno' => ['nullable', 'numeric', 'min:0'],
            'adicional_noturno' => ['nullable', 'numeric', 'min:0'],
            'porcentagem_insalubridade' => ['nullable', 'integer', 'in:10,20,40'],
            'porcentagem_periculosidade' => ['nullable', 'integer', 'in:30'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }

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
                    ]
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('regra_negocio', $e->getMessage());
            }
        });
    }

    public function messages(): array
    {
        return [
            'servidor_id.required' => 'Selecione um servidor.',
            'servidor_id.exists' => 'Servidor inválido.',
            'evento_id.required' => 'Selecione um evento.',
            'evento_id.exists' => 'Evento inválido.',
            'dias_trabalhados.integer' => 'Dias trabalhados deve ser um número inteiro.',
            'dias_trabalhados.min' => 'Dias trabalhados não pode ser negativo.',
            'valor.numeric' => 'Valor deve ser um número.',
            'valor.min' => 'Valor não pode ser negativo.',
            'observacao.max' => 'Observação não pode ter mais de 1000 caracteres.',
        ];
    }
}
