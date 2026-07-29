<?php

namespace App\Http\Requests;

use App\Enums\VinculoServidor;
use App\Models\Servidor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Servidor::class) ?? false;
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
            'origem_registro' => 'MANUAL',
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
                    if ($value && ! $this->validarCpf($value)) {
                        $fail('O CPF informado é inválido.');
                    }
                },
            ],
            'nome' => ['required', 'string', 'max:255'],
            'setor_id' => ['required', 'exists:setores,id'],
            'data_admissao' => ['required', 'date', 'before_or_equal:today'],
            'tipo_vinculo' => ['required', Rule::enum(VinculoServidor::class)],
            'cargo' => ['required', 'string', 'max:150'],
            'carga_horaria' => ['required', 'integer', 'min:1', 'max:80'],
            'ato_referencia' => ['nullable', 'string', 'max:255'],
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
            'data_admissao.required' => 'A data de admissão é obrigatória.',
            'tipo_vinculo.required' => 'O vínculo funcional é obrigatório.',
            'cargo.required' => 'O cargo é obrigatório.',
            'carga_horaria.required' => 'A carga horária semanal é obrigatória.',
        ];
    }
}
