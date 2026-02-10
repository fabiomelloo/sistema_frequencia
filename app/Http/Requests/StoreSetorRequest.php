<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSetorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isCentral();
    }

    protected function prepareForValidation(): void
    {
        // Transformar checkbox ausente em false
        $this->merge([
            'ativo' => $this->has('ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:10', 'unique:setores,sigla'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'sigla.required' => 'A sigla é obrigatória.',
            'sigla.max' => 'A sigla não pode ter mais de 10 caracteres.',
        ];
    }
}
