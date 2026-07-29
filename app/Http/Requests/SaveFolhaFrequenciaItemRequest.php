<?php

namespace App\Http\Requests;

use App\Enums\OrigemInformacaoItem;
use App\Enums\UnidadeLancamento;
use App\Models\EventoFolha;
use App\Models\FolhaFrequenciaItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFolhaFrequenciaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->podeFazerLancamentos() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('item')) {
            $this->errorBag = 'item_'.$this->route('item')->id;
        }

        $this->merge([
            'conteudo' => is_string($this->input('conteudo')) ? trim($this->input('conteudo')) : $this->input('conteudo'),
            'observacao' => is_string($this->input('observacao')) ? trim($this->input('observacao')) : $this->input('observacao'),
            'referencia_documento' => is_string($this->input('referencia_documento')) ? trim($this->input('referencia_documento')) : $this->input('referencia_documento'),
        ]);
    }

    public function rules(): array
    {
        return [
            'evento_id' => [$this->route('frequenciaItem') ? 'nullable' : 'required', 'nullable', 'integer', 'exists:eventos_folha,id'],
            'conteudo' => ['nullable', 'string', 'max:2000'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'referencia_documento' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $evento = $this->evento();
            $folha = $this->route('folha');
            $servidorFolha = $this->route('item');
            $registro = $this->route('frequenciaItem');

            if (! $folha || ! $servidorFolha) {
                return;
            }

            if (! $evento) {
                if ($registro) {
                    $validator->errors()->add('evento_id', 'A regra original deste item não está mais disponível no catálogo.');
                }

                return;
            }

            if ($servidorFolha->folha_frequencia_id !== $folha->id) {
                $validator->errors()->add('evento_id', 'O servidor não pertence a esta frequência.');

                return;
            }

            if ($registro && $registro->folha_frequencia_servidor_id !== $servidorFolha->id) {
                $validator->errors()->add('evento_id', 'O item não pertence ao servidor informado.');

                return;
            }

            if (! $evento->ativo || ! $evento->regra_validada || $evento->origem_informacao !== OrigemInformacaoItem::SETOR_MENSAL) {
                $validator->errors()->add('evento_id', 'Selecione um item mensal ativo e com regra homologada.');

                return;
            }

            if (! $evento->setoresComDireito()->where('setores.id', $folha->setor_id)->exists()) {
                $validator->errors()->add('evento_id', 'Este item não está autorizado para o setor da frequência.');
            }

            $duplicado = $servidorFolha->itens()->where('evento_id', $evento->id)
                ->when($registro, fn ($query) => $query->where('id', '!=', $registro->id))
                ->exists();
            if ($duplicado) {
                $validator->errors()->add('evento_id', 'Este item já foi lançado para o servidor nesta competência.');
            }

            $this->validarConteudo($validator, $evento);

            if ($evento->exige_observacao && blank($this->input('observacao'))) {
                $validator->errors()->add('observacao', 'A observação é obrigatória para este item.');
            }
            if ($evento->exige_documento && blank($this->input('referencia_documento'))) {
                $validator->errors()->add('referencia_documento', 'Informe a referência do documento exigido pela regra.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function dadosDoConteudo(): array
    {
        $evento = $this->evento();
        $conteudo = $this->input('conteudo');
        $dados = [
            'percentual' => null,
            'valor' => null,
            'quantidade' => null,
            'nivel' => null,
            'texto' => null,
            'observacao' => $this->input('observacao') ?: null,
            'referencia_documento' => $this->input('referencia_documento') ?: null,
        ];

        match ($evento->unidade_lancamento) {
            UnidadeLancamento::PERCENTUAL => $dados['percentual'] = $this->numero($conteudo),
            UnidadeLancamento::VALOR => $dados['valor'] = $this->numero($conteudo),
            UnidadeLancamento::DIAS, UnidadeLancamento::HORAS => $dados['quantidade'] = $this->numero($conteudo),
            UnidadeLancamento::NIVEL => $dados['nivel'] = $conteudo,
            UnidadeLancamento::TEXTO => $dados['texto'] = $conteudo,
            UnidadeLancamento::MARCADOR => null,
        };

        return $dados;
    }

    public function evento(): ?EventoFolha
    {
        $registro = $this->route('frequenciaItem');
        if ($registro instanceof FolhaFrequenciaItem) {
            return $registro->evento;
        }

        return EventoFolha::find($this->integer('evento_id'));
    }

    private function validarConteudo(Validator $validator, EventoFolha $evento): void
    {
        if ($evento->unidade_lancamento === UnidadeLancamento::MARCADOR) {
            return;
        }

        $conteudo = $this->input('conteudo');
        if (blank($conteudo)) {
            $validator->errors()->add('conteudo', 'Informe o conteúdo exigido pela forma de lançamento.');

            return;
        }

        if (! in_array($evento->unidade_lancamento, [UnidadeLancamento::PERCENTUAL, UnidadeLancamento::VALOR, UnidadeLancamento::DIAS, UnidadeLancamento::HORAS], true)) {
            return;
        }

        $numero = $this->numero($conteudo);
        if ($numero === null) {
            $validator->errors()->add('conteudo', 'Informe um número válido. Use vírgula ou ponto para os decimais.');

            return;
        }
        if ($numero < 0 || ($evento->unidade_lancamento !== UnidadeLancamento::VALOR && $numero == 0.0)) {
            $validator->errors()->add('conteudo', 'Informe um número maior que zero.');
        }
        if ($evento->unidade_lancamento === UnidadeLancamento::DIAS && floor($numero) !== $numero) {
            $validator->errors()->add('conteudo', 'A quantidade de dias deve ser um número inteiro.');
        }
        if ($evento->unidade_lancamento === UnidadeLancamento::PERCENTUAL && $numero > 100) {
            $validator->errors()->add('conteudo', 'O percentual não pode ultrapassar 100%.');
        }
        if ($evento->unidade_lancamento === UnidadeLancamento::DIAS && $evento->dias_maximo !== null && $numero > $evento->dias_maximo) {
            $validator->errors()->add('conteudo', "A regra permite no máximo {$evento->dias_maximo} dias.");
        }
        if ($evento->unidade_lancamento === UnidadeLancamento::VALOR) {
            if ($evento->valor_minimo !== null && $numero < (float) $evento->valor_minimo) {
                $validator->errors()->add('conteudo', "O valor mínimo permitido é R$ {$evento->valor_minimo}.");
            }
            if ($evento->valor_maximo !== null && $numero > (float) $evento->valor_maximo) {
                $validator->errors()->add('conteudo', "O valor máximo permitido é R$ {$evento->valor_maximo}.");
            }
        }
    }

    private function numero(mixed $valor): ?float
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $normalizado = str_replace(' ', '', (string) $valor);
        if (str_contains($normalizado, ',') && str_contains($normalizado, '.')) {
            $normalizado = str_replace('.', '', $normalizado);
        }
        $normalizado = str_replace(',', '.', $normalizado);

        return is_numeric($normalizado) ? (float) $normalizado : null;
    }
}
