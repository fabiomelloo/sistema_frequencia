<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvidenciaDocumentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->podeFazerLancamentos() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $alvo = $this->route('frequenciaItem') ?? $this->route('ocorrencia');
        if ($alvo) {
            $this->errorBag = 'evidencia_'.$alvo->getTable().'_'.$alvo->id;
        }

        $this->merge([
            'descricao' => is_string($this->input('descricao')) ? trim($this->input('descricao')) : $this->input('descricao'),
        ]);
    }

    public function rules(): array
    {
        return [
            'arquivo' => [
                'required',
                'file',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],
            'descricao' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'Selecione o documento comprobatório.',
            'arquivo.file' => 'O envio deve conter um arquivo válido.',
            'arquivo.mimetypes' => 'Envie um arquivo PDF, JPG ou PNG.',
            'arquivo.max' => 'O documento não pode ultrapassar 10 MB.',
            'descricao.max' => 'A descrição não pode ultrapassar 500 caracteres.',
        ];
    }

    public function errorBagName(): string
    {
        return $this->errorBag;
    }
}
