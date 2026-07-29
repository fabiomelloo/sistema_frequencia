<?php

namespace App\Http\Requests;

use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoEvento;
use App\Enums\UnidadeLancamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class EventoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $exigeDias = $this->boolean('exige_dias');
        $exigeValor = $this->boolean('exige_valor');

        $this->merge([
            'exige_dias' => $exigeDias,
            'exige_valor' => $exigeValor,
            'exige_observacao' => $this->boolean('exige_observacao'),
            'exige_porcentagem' => $this->boolean('exige_porcentagem'),
            'exige_documento' => $this->boolean('exige_documento'),
            'gera_efeito_financeiro' => $this->boolean('gera_efeito_financeiro'),
            'regra_validada' => $this->boolean('regra_validada'),
            'ativo' => $this->boolean('ativo'),
            'sigla' => $this->filled('sigla') ? mb_strtoupper(trim((string) $this->input('sigla'))) : null,
        ]);

        if (! $exigeValor) {
            $this->merge(['valor_minimo' => null, 'valor_maximo' => null]);
        }
        if (! $exigeDias) {
            $this->merge(['dias_maximo' => null]);
        }
    }

    /** @return array<string, mixed> */
    protected function regrasComuns(): array
    {
        return [
            'sigla' => ['nullable', 'string', 'max:20'],
            'tipo_evento' => ['required', Rule::enum(TipoEvento::class)],
            'descricao' => ['required', 'string', 'max:255'],
            'unidade_lancamento' => ['required', Rule::enum(UnidadeLancamento::class)],
            'origem_informacao' => ['required', Rule::enum(OrigemInformacaoItem::class)],
            'gera_efeito_financeiro' => ['required', 'boolean'],
            'exige_dias' => ['required', 'boolean', 'accepted_if:unidade_lancamento,DIAS'],
            'exige_valor' => ['required', 'boolean', 'accepted_if:unidade_lancamento,VALOR'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0', 'required_if:exige_valor,true'],
            'valor_maximo' => ['nullable', 'numeric', 'min:0', 'gte:valor_minimo', 'required_if:exige_valor,true'],
            'dias_maximo' => ['nullable', 'integer', 'min:1', 'max:31'],
            'exige_observacao' => ['required', 'boolean'],
            'exige_porcentagem' => ['required', 'boolean', 'accepted_if:unidade_lancamento,PERCENTUAL'],
            'exige_documento' => ['required', 'boolean'],
            'instrucoes_lancamento' => ['nullable', 'string', 'max:2000', 'required_if:regra_validada,true', 'min:10'],
            'regra_validada' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_evento.required' => 'O código oficial do evento é obrigatório.',
            'codigo_evento.unique' => 'Este código de evento já está em uso.',
            'codigo_evento.max' => 'O código do evento não pode ter mais de 10 caracteres.',
            'sigla.max' => 'A sigla não pode ter mais de 20 caracteres.',
            'tipo_evento.required' => 'O tipo de evento é obrigatório.',
            'descricao.required' => 'A descrição é obrigatória.',
            'descricao.max' => 'A descrição não pode ter mais de 255 caracteres.',
            'unidade_lancamento.required' => 'Informe como o item será lançado.',
            'origem_informacao.required' => 'Informe a origem da informação.',
            'exige_dias.accepted_if' => 'Itens lançados em dias devem exigir a quantidade de dias.',
            'exige_valor.accepted_if' => 'Itens lançados em valor devem exigir o valor monetário.',
            'exige_porcentagem.accepted_if' => 'Itens percentuais devem exigir o percentual.',
            'valor_minimo.required_if' => 'Informe o valor mínimo quando o item exige valor.',
            'valor_maximo.required_if' => 'Informe o valor máximo quando o item exige valor.',
            'valor_maximo.gte' => 'O valor máximo deve ser maior ou igual ao valor mínimo.',
            'dias_maximo.max' => 'O limite não pode ser maior que 31 dias.',
            'instrucoes_lancamento.required_if' => 'Descreva a regra antes de marcá-la como validada.',
            'instrucoes_lancamento.min' => 'A instrução deve ter pelo menos 10 caracteres.',
            'instrucoes_lancamento.max' => 'A instrução não pode ultrapassar 2.000 caracteres.',
        ];
    }
}
