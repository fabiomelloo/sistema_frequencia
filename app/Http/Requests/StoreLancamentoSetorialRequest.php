<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\EventoFolha;

class StoreLancamentoSetorialRequest extends FormRequest
{
    private ?EventoFolha $eventoCache = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();
        $evento = $this->getEvento(); // Cache da query

        $rules = [
            'servidor_id' => [
                'required',
                'exists:servidores,id',
                function ($attribute, $value, $fail) use ($user) {
                    $servidor = \App\Models\Servidor::find($value);
                    if (!$servidor) {
                        return;
                    }
                    if ($servidor->setor_id !== $user->setor_id) {
                        $fail('Servidor não pertence ao seu setor.');
                    }
                    if (!$servidor->ativo) {
                        $fail('Servidor está inativo e não pode receber lançamentos.');
                    }
                },
            ],
            'evento_id' => [
                'required',
                'exists:eventos_folha,id',
                function ($attribute, $value, $fail) use ($user, $evento) {
                    if (!$evento) {
                        $fail('Evento inválido.');
                        return;
                    }
                    if (!$evento->ativo) {
                        $fail('Este evento está inativo e não pode ser utilizado.');
                    }
                    if (!$evento->temDireitoNoSetor($user->setor_id)) {
                        $fail('Seu setor não possui direito a este evento.');
                    }
                },
            ],
            'dias_trabalhados' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($evento) {
                    if ($evento && $evento->exige_dias && is_null($value)) {
                        $fail('Dias trabalhados é obrigatório para este evento.');
                    }
                    if ($evento && $evento->dias_maximo && $value > $evento->dias_maximo) {
                        $fail("Máximo de dias permitido: {$evento->dias_maximo}");
                    }
                },
            ],
            'dias_noturnos' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($value && $this->dias_trabalhados && $value > $this->dias_trabalhados) {
                        $fail('Dias noturnos não podem ser maiores que dias trabalhados.');
                    }
                },
            ],
            'valor' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($evento) {
                    if ($evento && $evento->exige_valor && is_null($value)) {
                        $fail('Valor é obrigatório para este evento.');
                    }
                    if ($evento && $evento->valor_minimo && $value < $evento->valor_minimo) {
                        $fail("Valor mínimo: R$ " . number_format($evento->valor_minimo, 2, ',', '.'));
                    }
                    if ($evento && $evento->valor_maximo && $value > $evento->valor_maximo) {
                        $fail("Valor máximo: R$ " . number_format($evento->valor_maximo, 2, ',', '.'));
                    }
                },
            ],
            'valor_gratificacao' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'porcentagem' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'adicional_turno' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'adicional_noturno' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'porcentagem_insalubridade' => [
                'nullable',
                'integer',
                'in:10,20,40',
                function ($attribute, $value, $fail) use ($evento) {
                    if ($evento && $evento->exige_porcentagem && is_null($value)) {
                        $fail('Porcentagem de insalubridade é obrigatória para este evento.');
                    }
                    if ($evento && !$evento->exige_porcentagem && !is_null($value)) {
                        $fail('Este evento não exige porcentagem de insalubridade.');
                    }
                },
            ],
            'porcentagem_periculosidade' => [
                'nullable',
                'integer',
                'in:30', // Periculosidade geralmente é 30%
            ],
            'observacao' => [
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) use ($evento) {
                    if ($evento && $evento->exige_observacao && is_null($value)) {
                        $fail('Observação é obrigatória para este evento.');
                    }
                },
            ],
        ];

        return $rules;
    }

    /**
     * Cache do evento para evitar múltiplas queries
     */
    protected function getEvento(): ?EventoFolha
    {
        if ($this->eventoCache === null && $this->has('evento_id')) {
            $this->eventoCache = EventoFolha::find($this->evento_id);
        }
        
        return $this->eventoCache;
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
