<?php

namespace App\Http\Requests;

use App\Enums\FrequenciaServidorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFrequenciaServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->podeFazerLancamentos() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FrequenciaServidorStatus::class)],
            'observacao_geral' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe a situação da frequência.',
            'observacao_geral.max' => 'A observação pode ter no máximo 1.000 caracteres.',
        ];
    }
}
