<?php

namespace App\Http\Requests;

use App\Enums\TipoOcorrenciaFrequencia;
use App\Models\Competencia;
use App\Models\Servidor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOcorrenciaFrequenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        if (! $user || (! $user->isSetorial() && ! $user->isGestor())) {
            return false;
        }

        $ocorrencia = $this->route('ocorrencia');

        return ! $ocorrencia || $user->can('update', $ocorrencia);
    }

    protected function prepareForValidation(): void
    {
        $dias = array_values(array_filter((array) $this->input('dias_especificos', [])));
        $this->merge([
            'dias_especificos' => $dias ?: null,
            'possui_comprovacao' => $this->boolean('possui_comprovacao'),
        ]);
    }

    public function rules(): array
    {
        return [
            'servidor_id' => ['required', 'integer', 'exists:servidores,id'],
            'competencia_id' => ['required', 'integer', 'exists:competencias,id'],
            'tipo' => ['required', Rule::enum(TipoOcorrenciaFrequencia::class)],
            'data_inicio' => ['nullable', 'date_format:Y-m-d', 'required_without:dias_especificos'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'required_with:data_inicio', 'after_or_equal:data_inicio'],
            'dias_especificos' => ['nullable', 'array'],
            'dias_especificos.*' => ['date_format:Y-m-d', 'distinct'],
            'justificada' => ['nullable', 'boolean'],
            'possui_comprovacao' => ['required', 'boolean'],
            'referencia_documento' => ['nullable', 'string', 'max:255'],
            'observacao_original' => ['nullable', 'string', 'max:2000'],
            'retorno' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->messages()->isNotEmpty()) {
                return;
            }

            $competencia = Competencia::find($this->integer('competencia_id'));
            $servidor = Servidor::find($this->integer('servidor_id'));
            $user = auth()->user();

            if (! $competencia || ! $servidor || ! $user) {
                return;
            }

            if (! $competencia->estaAberta()) {
                $validator->errors()->add('competencia_id', 'A competência selecionada está fechada.');
            }

            if ($servidor->setorNaCompetencia($competencia->referencia) !== $user->setor_id) {
                $validator->errors()->add('servidor_id', 'O servidor não pertence ao seu setor nesta competência.');
            }

            if (! $servidor->estaAtivoNaCompetencia($competencia->referencia)) {
                $validator->errors()->add('servidor_id', 'O servidor não está ativo no período desta competência.');
            }

            $inicio = $competencia->inicioPeriodo();
            $fim = $competencia->fimPeriodo();
            $datas = array_filter([
                $this->input('data_inicio'),
                $this->input('data_fim'),
                ...((array) $this->input('dias_especificos', [])),
            ]);

            foreach ($datas as $data) {
                if ($data < $inicio->toDateString() || $data > $fim->toDateString()) {
                    $validator->errors()->add('data_inicio', "A data {$data} está fora do período {$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}.");
                    break;
                }
            }

            if ($this->input('tipo') === TipoOcorrenciaFrequencia::OUTRO->value && ! $this->filled('observacao_original')) {
                $validator->errors()->add('observacao_original', 'Descreva a ocorrência quando o tipo for Outro.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'data_inicio.required_without' => 'Informe um período ou pelo menos um dia específico.',
            'data_fim.required_with' => 'Informe a data final do período.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'dias_especificos.*.distinct' => 'Não repita um mesmo dia específico.',
            'observacao_original.max' => 'A observação pode ter no máximo 2.000 caracteres.',
        ];
    }
}
