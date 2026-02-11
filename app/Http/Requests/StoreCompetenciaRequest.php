<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompetenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'referencia' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'data_limite' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'referencia.required' => 'A referência é obrigatória.',
            'referencia.regex' => 'A referência deve estar no formato YYYY-MM.',
            'data_limite.date' => 'Data limite inválida.',
            'data_limite.after' => 'A data limite deve ser posterior a hoje.',
        ];
    }
}
