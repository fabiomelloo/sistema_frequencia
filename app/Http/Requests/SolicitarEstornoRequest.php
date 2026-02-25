<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarEstornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isSetorial();
    }

    public function rules(): array
    {
        return [
            'motivo_estorno' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_estorno.required' => 'O motivo da solicitação de estorno é obrigatório.',
            'motivo_estorno.min' => 'O motivo deve ter pelo menos 5 caracteres.',
            'motivo_estorno.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ];
    }
}
