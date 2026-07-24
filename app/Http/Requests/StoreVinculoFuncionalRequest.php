<?php

namespace App\Http\Requests;

use App\Enums\VinculoServidor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVinculoFuncionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servidor = $this->route('servidor');

        return $servidor && ($this->user()?->can('update', $servidor) ?? false);
    }

    public function rules(): array
    {
        return [
            'tipo_vinculo' => ['required', Rule::enum(VinculoServidor::class)],
            'cargo' => ['required', 'string', 'max:150'],
            'carga_horaria' => ['required', 'integer', 'min:1', 'max:80'],
            'data_inicio' => ['required', 'date', 'before_or_equal:today'],
            'ato_referencia' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_vinculo.required' => 'Informe o vínculo funcional.',
            'cargo.required' => 'Informe o cargo vigente.',
            'carga_horaria.required' => 'Informe a carga horária semanal.',
            'carga_horaria.max' => 'A carga horária semanal não pode ultrapassar 80 horas.',
            'data_inicio.required' => 'Informe o início da vigência.',
            'data_inicio.before_or_equal' => 'O novo vínculo não pode começar em data futura.',
        ];
    }
}
