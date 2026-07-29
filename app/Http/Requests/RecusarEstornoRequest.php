<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecusarEstornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_recusa' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_recusa.required' => 'O motivo da recusa é obrigatório.',
            'motivo_recusa.min' => 'O motivo da recusa deve ter pelo menos 10 caracteres.',
            'motivo_recusa.max' => 'O motivo da recusa não pode ter mais de 1000 caracteres.',
        ];
    }
}
