<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportarLancamentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    public function rules(): array
    {
        return [
            'competencia' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'competencia.required' => 'Selecione uma competência para exportar.',
            'competencia.regex' => 'A competência deve estar no formato YYYY-MM (ex: 2026-03).',
        ];
    }
}
