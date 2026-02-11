<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isCentral();
    }

    public function rules(): array
    {
        return [
            'setor_id' => ['required', 'exists:setores,id'],
            'evento_id' => ['required', 'exists:eventos_folha,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'setor_id.required' => 'O setor é obrigatório.',
            'setor_id.exists' => 'Setor inválido.',
            'evento_id.required' => 'O evento é obrigatório.',
            'evento_id.exists' => 'Evento inválido.',
        ];
    }
}
