<?php

namespace App\Services;

use App\Enums\OrigemInformacaoItem;
use App\Models\EventoFolha;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FolhaFrequenciaItemService
{
    public function criar(
        FolhaFrequenciaServidor $servidorFolha,
        EventoFolha $evento,
        array $dados,
        User $usuario,
    ): FolhaFrequenciaItem {
        $this->garantirEdicao($servidorFolha);
        $folha = $servidorFolha->folha;
        if (! $evento->ativo || ! $evento->regra_validada || $evento->origem_informacao !== OrigemInformacaoItem::SETOR_MENSAL) {
            throw new InvalidArgumentException('O item não está homologado para lançamento mensal pelo setor.');
        }
        if (! $evento->setoresComDireito()->where('setores.id', $folha->setor_id)->exists()) {
            throw new InvalidArgumentException('O item não está autorizado para o setor da frequência.');
        }

        return DB::transaction(function () use ($servidorFolha, $evento, $dados, $usuario): FolhaFrequenciaItem {
            $duplicado = $servidorFolha->itens()->where('evento_id', $evento->id)->lockForUpdate()->exists();
            if ($duplicado) {
                throw new InvalidArgumentException('Este item já foi lançado para o servidor nesta competência.');
            }

            return $servidorFolha->itens()->create($this->snapshotEvento($evento) + $dados + [
                'evento_id' => $evento->id,
                'editavel_pelo_setor' => true,
                'atualizado_por_id' => $usuario->id,
            ]);
        });
    }

    public function atualizar(FolhaFrequenciaItem $item, array $dados, User $usuario): FolhaFrequenciaItem
    {
        $this->garantirEdicao($item->servidorFolha);
        if (! $item->editavel_pelo_setor || $item->origem_informacao !== OrigemInformacaoItem::SETOR_MENSAL) {
            throw new InvalidArgumentException('Itens originados do cadastro funcional não podem ser alterados pelo setor.');
        }

        $item->update($dados + ['atualizado_por_id' => $usuario->id]);

        return $item->refresh();
    }

    public function remover(FolhaFrequenciaItem $item): void
    {
        $this->garantirEdicao($item->servidorFolha);
        if (! $item->editavel_pelo_setor || $item->origem_informacao !== OrigemInformacaoItem::SETOR_MENSAL) {
            throw new InvalidArgumentException('Itens originados do cadastro funcional não podem ser removidos pelo setor.');
        }

        $item->delete();
    }

    /** @return array<string, mixed> */
    public function snapshotEvento(EventoFolha $evento): array
    {
        return [
            'codigo_evento' => $evento->codigo_evento,
            'sigla' => $evento->sigla,
            'descricao' => $evento->descricao,
            'unidade_lancamento' => $evento->unidade_lancamento,
            'origem_informacao' => $evento->origem_informacao,
            'gera_efeito_financeiro' => $evento->gera_efeito_financeiro,
            'regra_validada_snapshot' => $evento->regra_validada,
            'exige_documento' => $evento->exige_documento,
        ];
    }

    private function garantirEdicao(FolhaFrequenciaServidor $servidorFolha): void
    {
        $folha = $servidorFolha->folha;
        if (! $folha->editavelPeloSetor() || ! $folha->competencia->estaAberta()) {
            throw new InvalidArgumentException('A frequência não está aberta para alteração de itens.');
        }
    }
}
