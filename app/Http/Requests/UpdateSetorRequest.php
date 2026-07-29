<?php

namespace App\Http\Requests;

use App\Models\Setor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSetorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setor = $this->route('setor');

        return $setor && ($this->user()?->can('update', $setor) ?? false);
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
        $setorId = $this->route('setor')->id;

        return [
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:10', 'unique:setores,sigla,'.$setorId],
            'codigo_externo' => ['nullable', 'string', 'max:50'],
            'setor_pai_id' => [
                'nullable',
                'integer',
                'exists:setores,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($setorId): void {
                    if (! $value) {
                        return;
                    }

                    if ((int) $value === $setorId) {
                        $fail('Um setor não pode ser subordinado a ele mesmo.');

                        return;
                    }

                    $pai = Setor::find($value);
                    if ($pai?->possuiAncestral($setorId)) {
                        $fail('A hierarquia informada criaria um ciclo entre setores.');
                    }
                },
            ],
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
