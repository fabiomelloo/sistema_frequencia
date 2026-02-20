<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCentral();
    }

    protected function prepareForValidation(): void
    {
        // Transformar checkbox ausente em false
        // Limpar CPF (remover formatação)
        $cpf = $this->input('cpf');
        if ($cpf) {
            $this->merge([
                'cpf' => preg_replace('/\D/', '', $cpf),
            ]);
        }

        $this->merge([
            'ativo' => $this->has('ativo'),
            'origem_registro' => $this->input('origem_registro') ?? 'MANUAL',
        ]);
    }

    public function rules(): array
    {
        return [
            'matricula' => ['required', 'string', 'max:50', 'unique:servidores,matricula'],
            'cpf' => [
                'nullable',
                'string',
                'size:11',
                'regex:/^\d{11}$/',
                'unique:servidores,cpf',
                function ($attribute, $value, $fail) {
                    if ($value && !$this->validarCpf($value)) {
                        $fail('O CPF informado é inválido.');
                    }
                },
            ],
            'nome' => ['required', 'string', 'max:255'],
            'setor_id' => ['required', 'exists:setores,id'],
            'origem_registro' => ['nullable', 'string', 'max:255'],
            'ativo' => ['required', 'boolean'],
            'funcao_vigia' => ['nullable', 'boolean'],
            'trabalha_noturno' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Valida CPF usando algoritmo de validação.
     */
    private function validarCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;

        if (intval($cpf[9]) != $digito1) {
            return false;
        }

        // Valida segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;

        return intval($cpf[10]) == $digito2;
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
