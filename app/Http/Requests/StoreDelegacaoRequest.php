<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDelegacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isSetorial();
    }

    public function rules(): array
    {
        return [
            'delegado_id' => ['required', 'exists:users,id'],
            'data_inicio' => ['required', 'date', 'after_or_equal:today'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'delegado_id.required' => 'Selecione o usuário delegado.',
            'delegado_id.exists' => 'Usuário inválido.',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_inicio.date' => 'Data de início inválida.',
            'data_inicio.after_or_equal' => 'A data de início deve ser hoje ou posterior.',
            'data_fim.required' => 'A data de fim é obrigatória.',
            'data_fim.after' => 'A data de fim deve ser posterior à data de início.',
            'motivo.max' => 'O motivo não pode ter mais de 500 caracteres.',
        ];
    }
}
