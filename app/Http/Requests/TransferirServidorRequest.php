<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferirServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'novo_setor_id' => [
                'required',
                'integer',
                'exists:setores,id',
                function ($attribute, $value, $fail) {
                    $servidor = $this->route('servidor');
                    if ($servidor->setor_id == $value) {
                        $fail('O servidor já pertence a este setor.');
                    }
                },
            ],
            'data_transferencia' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'motivo' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'novo_setor_id.required' => 'O novo setor é obrigatório.',
            'novo_setor_id.exists' => 'Setor inválido.',
            'data_transferencia.required' => 'A data de transferência é obrigatória.',
            'data_transferencia.date' => 'Data de transferência inválida.',
            'data_transferencia.after_or_equal' => 'A data de transferência deve ser hoje ou posterior.',
            'motivo.max' => 'O motivo não pode ter mais de 500 caracteres.',
        ];
    }
}
