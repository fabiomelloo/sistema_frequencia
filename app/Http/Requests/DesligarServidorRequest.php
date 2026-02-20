<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DesligarServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'data_desligamento' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'motivo' => [
                'required',
                'string',
                'in:EXONERACAO,APOSENTADORIA,DEMISSAO,OBITO,OUTRO',
            ],
            'motivo_detalhado' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:motivo,OUTRO',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'data_desligamento.required' => 'A data de desligamento é obrigatória.',
            'data_desligamento.date' => 'Data de desligamento inválida.',
            'data_desligamento.after_or_equal' => 'A data de desligamento deve ser hoje ou posterior.',
            'motivo.required' => 'O motivo do desligamento é obrigatório.',
            'motivo.in' => 'Motivo inválido. Deve ser: EXONERACAO, APOSENTADORIA, DEMISSAO, OBITO ou OUTRO.',
            'motivo_detalhado.required_if' => 'Informe o motivo detalhado quando selecionar OUTRO.',
            'motivo_detalhado.max' => 'O motivo detalhado não pode ter mais de 1000 caracteres.',
        ];
    }

    /**
     * Retorna o motivo final (usando motivo_detalhado se motivo for OUTRO).
     */
    public function getMotivoFinal(): string
    {
        if ($this->motivo === 'OUTRO') {
            return $this->motivo_detalhado ?? 'Outro motivo';
        }
        return $this->motivo;
    }
}
