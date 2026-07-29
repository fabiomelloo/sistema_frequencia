<?php

namespace App\Http\Requests;

use App\Enums\CompetenciaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolhaFrequenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->podeFazerLancamentos() ?? false;
    }

    public function rules(): array
    {
        return [
            'competencia_id' => [
                'required',
                'integer',
                Rule::exists('competencias', 'id')->where('status', CompetenciaStatus::ABERTA->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'competencia_id.required' => 'Selecione uma competência.',
            'competencia_id.exists' => 'A competência selecionada não existe ou está fechada.',
        ];
    }
}
