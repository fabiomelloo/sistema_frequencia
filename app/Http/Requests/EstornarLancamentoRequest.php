<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstornarLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'motivo_estorno' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_estorno.required' => 'O motivo do estorno é obrigatório.',
            'motivo_estorno.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_estorno.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ];
    }
}
