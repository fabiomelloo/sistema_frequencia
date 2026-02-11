<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isSetorial();
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'O arquivo CSV é obrigatório.',
            'arquivo.file' => 'O upload deve ser um arquivo válido.',
            'arquivo.mimes' => 'O arquivo deve ser do tipo CSV ou TXT.',
            'arquivo.max' => 'O arquivo não pode ter mais de 5MB.',
        ];
    }
}
