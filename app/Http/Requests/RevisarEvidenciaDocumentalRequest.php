<?php

namespace App\Http\Requests;

use App\Enums\EvidenciaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisarEvidenciaDocumentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->temAcessoPainel() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('evidencia')) {
            $this->errorBag = 'revisao_evidencia_'.$this->route('evidencia')->id;
        }

        $this->merge([
            'motivo_revisao' => is_string($this->input('motivo_revisao')) ? trim($this->input('motivo_revisao')) : $this->input('motivo_revisao'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([EvidenciaStatus::ACEITA->value, EvidenciaStatus::RECUSADA->value])],
            'motivo_revisao' => [
                'nullable',
                'string',
                'min:10',
                'max:2000',
                Rule::requiredIf($this->input('status') === EvidenciaStatus::RECUSADA->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o resultado da análise do documento.',
            'motivo_revisao.required' => 'Explique objetivamente por que o documento foi recusado.',
            'motivo_revisao.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_revisao.max' => 'O motivo não pode ultrapassar 2.000 caracteres.',
        ];
    }

    public function errorBagName(): string
    {
        return $this->errorBag;
    }
}
