<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstornarLancamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_estorno' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_estorno.string' => 'O motivo deve ser um texto válido.',
            'motivo_estorno.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_estorno.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ];
    }
}
