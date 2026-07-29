<?php

namespace App\Http\Requests;

use App\Enums\ConferenciaServidorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConferirFrequenciaServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('item')) {
            $this->errorBag = 'conferencia_'.$this->route('item')->id;
        }

        $this->merge([
            'apontamento' => is_string($this->input('apontamento')) ? trim($this->input('apontamento')) : $this->input('apontamento'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ConferenciaServidorStatus::class)],
            'apontamento' => [
                'nullable',
                'string',
                'min:10',
                'max:2000',
                Rule::requiredIf($this->input('status') === ConferenciaServidorStatus::DIVERGENTE->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o resultado da conferência do servidor.',
            'apontamento.required' => 'Descreva objetivamente a divergência encontrada.',
            'apontamento.min' => 'O apontamento deve ter pelo menos 10 caracteres.',
            'apontamento.max' => 'O apontamento não pode ultrapassar 2.000 caracteres.',
        ];
    }
}
