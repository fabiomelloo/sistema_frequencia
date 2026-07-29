<?php

namespace App\Http\Requests;

use App\Services\ConfiguracaoSistemaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateConfiguracoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return app(ConfiguracaoSistemaService::class)->regras();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $recebidas = array_keys($this->except(['_token', '_method']));
            $permitidas = app(ConfiguracaoSistemaService::class)->chavesEditaveis();

            if (array_diff($recebidas, $permitidas) !== []) {
                $validator->errors()->add(
                    'configuracoes',
                    'A requisição contém parâmetros de configuração não autorizados.'
                );
            }
        });
    }
}
