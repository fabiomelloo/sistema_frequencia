<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServidorRequest extends FormRequest
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
            'origem_registro' => $this->input('origem_registro') ?? $this->route('servidor')->origem_registro ?? 'MANUAL',
        ]);
    }

    public function rules(): array
    {
        $servidorId = $this->route('servidor')->id;

        return [
            'matricula' => ['required', 'string', 'max:50', 'unique:servidores,matricula,' . $servidorId],
            'nome' => ['required', 'string', 'max:255'],
            'setor_id' => ['required', 'exists:setores,id'],
            'origem_registro' => ['nullable', 'string', 'max:255'],
            'ativo' => ['required', 'boolean'],
            'funcao_vigia' => ['nullable', 'boolean'],
            'trabalha_noturno' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'matricula.required' => 'A matrícula é obrigatória.',
            'matricula.unique' => 'Esta matrícula já está cadastrada.',
            'matricula.max' => 'A matrícula não pode ter mais de 50 caracteres.',
            'nome.required' => 'O nome é obrigatório.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'setor_id.required' => 'O setor é obrigatório.',
            'setor_id.exists' => 'Setor inválido.',
            'origem_registro.max' => 'A origem do registro não pode ter mais de 255 caracteres.',
        ];
    }
}
