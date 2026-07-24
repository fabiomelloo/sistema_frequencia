<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DevolverFolhaFrequenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_devolucao' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_devolucao.required' => 'Informe o que o setor precisa corrigir.',
            'motivo_devolucao.min' => 'A orientação de correção deve ter pelo menos 10 caracteres.',
            'motivo_devolucao.max' => 'A orientação de correção não pode ultrapassar 2000 caracteres.',
        ];
    }
}
