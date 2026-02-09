<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Transformar checkboxes ausentes em false
        $this->merge([
            'exige_dias' => $this->has('exige_dias'),
            'exige_valor' => $this->has('exige_valor'),
            'exige_observacao' => $this->has('exige_observacao'),
            'exige_porcentagem' => $this->has('exige_porcentagem'),
            'ativo' => $this->has('ativo'),
        ]);

        // Transformar strings vazias em null
        if ($this->valor_minimo === '') {
            $this->merge(['valor_minimo' => null]);
        }
        if ($this->valor_maximo === '') {
            $this->merge(['valor_maximo' => null]);
        }
        if ($this->dias_maximo === '') {
            $this->merge(['dias_maximo' => null]);
        }
    }

    public function rules(): array
    {
        $eventoId = $this->route('evento')->id;

        $rules = [
            'codigo_evento' => ['required', 'string', 'max:20', 'unique:eventos_folha,codigo_evento,' . $eventoId],
            'tipo_evento' => ['required', 'string', 'in:' . implode(',', \App\Enums\TipoEvento::valores())],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['required', 'boolean'],
            'exige_valor' => ['required', 'boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => ['nullable', 'numeric', 'min:0', 'gte:valor_minimo'],
            'dias_maximo' => ['nullable', 'integer', 'min:1'],
            'exige_observacao' => ['required', 'boolean'],
            'exige_porcentagem' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'codigo_evento.required' => 'O código do evento é obrigatório.',
            'codigo_evento.unique' => 'Este código de evento já está em uso.',
            'codigo_evento.max' => 'O código do evento não pode ter mais de 20 caracteres.',
            'tipo_evento.required' => 'O tipo de evento é obrigatório.',
            'tipo_evento.in' => 'Tipo de evento inválido.',
            'descricao.required' => 'A descrição é obrigatória.',
            'descricao.max' => 'A descrição não pode ter mais de 255 caracteres.',
            'valor_minimo.numeric' => 'O valor mínimo deve ser um número.',
            'valor_minimo.min' => 'O valor mínimo não pode ser negativo.',
            'valor_maximo.numeric' => 'O valor máximo deve ser um número.',
            'valor_maximo.min' => 'O valor máximo não pode ser negativo.',
            'valor_maximo.gte' => 'O valor máximo deve ser maior ou igual ao valor mínimo.',
            'dias_maximo.integer' => 'O dias máximo deve ser um número inteiro.',
            'dias_maximo.min' => 'O dias máximo deve ser pelo menos 1.',
        ];
    }
}
