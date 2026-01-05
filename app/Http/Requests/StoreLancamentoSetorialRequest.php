<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\EventoFolha;

class StoreLancamentoSetorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();

        return [
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
                function ($attribute, $value, $fail) use ($user) {
                    $evento = EventoFolha::find($value);
                    if (!$evento) {
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
            'dias_lancados' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
                    $evento = EventoFolha::find($this->evento_id);
                    if ($evento && $evento->exige_dias && is_null($value)) {
                        $fail('Dias lançados é obrigatório para este evento.');
                    }
                    if ($evento && $evento->dias_maximo && $value > $evento->dias_maximo) {
                        $fail("Máximo de dias permitido: {$evento->dias_maximo}");
                    }
                },
            ],
            'valor' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $evento = EventoFolha::find($this->evento_id);
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
            'porcentagem_insalubridade' => [
                'nullable',
                'integer',
                'in:10,20,40',
                function ($attribute, $value, $fail) {
                    $evento = EventoFolha::find($this->evento_id);
                    if ($evento && $evento->exige_porcentagem && is_null($value)) {
                        $fail('Porcentagem de insalubridade é obrigatória para este evento.');
                    }
                    if ($evento && !$evento->exige_porcentagem && !is_null($value)) {
                        $fail('Este evento não exige porcentagem de insalubridade.');
                    }
                },
            ],
            'observacao' => [
                'nullable',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) {
                    $evento = EventoFolha::find($this->evento_id);
                    if ($evento && $evento->exige_observacao && is_null($value)) {
                        $fail('Observação é obrigatória para este evento.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required' => 'Selecione um servidor.',
            'servidor_id.exists' => 'Servidor inválido.',
            'evento_id.required' => 'Selecione um evento.',
            'evento_id.exists' => 'Evento inválido.',
            'dias_lancados.integer' => 'Dias deve ser um número inteiro.',
            'dias_lancados.min' => 'Dias não pode ser negativo.',
            'valor.numeric' => 'Valor deve ser um número.',
            'valor.min' => 'Valor não pode ser negativo.',
            'observacao.max' => 'Observação não pode ter mais de 1000 caracteres.',
        ];
    }
}
