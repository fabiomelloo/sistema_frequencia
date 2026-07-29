<?php

namespace App\Http\Requests;

use App\Enums\TipoDesignacaoFuncional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDesignacaoFuncionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servidor = $this->route('servidor');

        return $servidor && ($this->user()?->can('update', $servidor) ?? false);
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoDesignacaoFuncional::class)],
            'nivel' => ['nullable', 'string', 'max:30'],
            'descricao' => ['required', 'string', 'max:150'],
            'data_inicio' => ['required', 'date', 'before_or_equal:today'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'ato_referencia' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
