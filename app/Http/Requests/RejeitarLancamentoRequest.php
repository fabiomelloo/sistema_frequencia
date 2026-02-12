<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejeitarLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'motivo_rejeicao' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_rejeicao.required' => 'O motivo da rejeição é obrigatório.',
            'motivo_rejeicao.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_rejeicao.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ];
    }
}
