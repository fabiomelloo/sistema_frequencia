<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AprovarEmLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'lancamento_ids' => ['required', 'array', 'min:1'],
            'lancamento_ids.*' => ['integer', 'exists:lancamentos_setoriais,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'lancamento_ids.required' => 'Selecione pelo menos um lançamento.',
            'lancamento_ids.array' => 'Os lançamentos devem ser uma lista.',
            'lancamento_ids.min' => 'Selecione pelo menos um lançamento.',
            'lancamento_ids.*.integer' => 'ID de lançamento inválido.',
            'lancamento_ids.*.exists' => 'Lançamento não encontrado.',
        ];
    }
}
