<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EncerrarVigenciaFuncionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servidor = $this->route('servidor');

        return $servidor && ($this->user()?->can('update', $servidor) ?? false);
    }

    public function rules(): array
    {
        return [
            'data_fim' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
