<?php

namespace App\Http\Requests;

use App\Enums\OrigemInformacaoItem;
use App\Enums\UnidadeLancamento;
use App\Models\EventoFolha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class StoreVantagemFuncionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servidor = $this->route('servidor');

        return $servidor && ($this->user()?->can('update', $servidor) ?? false);
    }

    public function rules(): array
    {
        return [
            'evento_id' => ['required', 'integer', 'exists:eventos_folha,id'],
            'percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'quantidade' => ['nullable', 'numeric', 'gt:0'],
            'nivel' => ['nullable', 'string', 'max:30'],
            'texto' => ['nullable', 'string', 'max:1000'],
            'data_inicio' => ['required', 'date', 'before_or_equal:today'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'referencia_documento' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $evento = EventoFolha::find($this->integer('evento_id'));
            if (! $evento) {
                return;
            }

            if (! $evento->ativo || ! $evento->regra_validada || $evento->origem_informacao !== OrigemInformacaoItem::CADASTRO_FUNCIONAL) {
                $validator->errors()->add('evento_id', 'Selecione um item ativo, validado e originado no cadastro funcional.');

                return;
            }

            $campoObrigatorio = match ($evento->unidade_lancamento) {
                UnidadeLancamento::PERCENTUAL => 'percentual',
                UnidadeLancamento::VALOR => 'valor',
                UnidadeLancamento::DIAS, UnidadeLancamento::HORAS => 'quantidade',
                UnidadeLancamento::NIVEL => 'nivel',
                UnidadeLancamento::TEXTO => 'texto',
                default => null,
            };

            if ($campoObrigatorio && blank($this->input($campoObrigatorio))) {
                $validator->errors()->add($campoObrigatorio, 'Este valor é obrigatório para a forma de lançamento configurada.');
            }

            if ($evento->exige_documento && blank($this->input('referencia_documento'))) {
                $validator->errors()->add('referencia_documento', 'Informe a referência do documento exigido por esta regra.');
            }

            if ($evento->unidade_lancamento === UnidadeLancamento::VALOR && $this->filled('valor')) {
                $valor = (float) $this->input('valor');
                if ($evento->valor_minimo !== null && $valor < (float) $evento->valor_minimo) {
                    $validator->errors()->add('valor', "O valor mínimo permitido é R$ {$evento->valor_minimo}.");
                }
                if ($evento->valor_maximo !== null && $valor > (float) $evento->valor_maximo) {
                    $validator->errors()->add('valor', "O valor máximo permitido é R$ {$evento->valor_maximo}.");
                }
            }

            if ($evento->unidade_lancamento === UnidadeLancamento::DIAS && $this->filled('quantidade') && $evento->dias_maximo !== null && (float) $this->input('quantidade') > $evento->dias_maximo) {
                $validator->errors()->add('quantidade', "A quantidade máxima permitida é {$evento->dias_maximo} dias.");
            }
        }];
    }

    /** @return array<string, mixed> */
    public function dadosParaPersistencia(): array
    {
        $dados = $this->validated();
        $evento = EventoFolha::findOrFail($dados['evento_id']);
        $campoDaUnidade = match ($evento->unidade_lancamento) {
            UnidadeLancamento::PERCENTUAL => 'percentual',
            UnidadeLancamento::VALOR => 'valor',
            UnidadeLancamento::DIAS, UnidadeLancamento::HORAS => 'quantidade',
            UnidadeLancamento::NIVEL => 'nivel',
            UnidadeLancamento::TEXTO => 'texto',
            default => null,
        };

        $campos = ['evento_id', 'data_inicio', 'data_fim', 'referencia_documento', 'observacao'];
        if ($campoDaUnidade) {
            $campos[] = $campoDaUnidade;
        }

        return Arr::only($dados, $campos);
    }
}
