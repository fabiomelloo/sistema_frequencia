<?php

namespace App\Services;

use App\Enums\FolhaFrequenciaStatus;
use App\Enums\ProjecaoExportacaoStatus;
use App\Enums\UnidadeLancamento;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\ProjecaoExportacaoFolha;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProjecaoExportacaoFolhaService
{
    /** @return Collection<int, ProjecaoExportacaoFolha> */
    public function projetar(FolhaFrequencia $folha): Collection
    {
        if (! in_array($folha->status, [
            FolhaFrequenciaStatus::FINALIZADA,
            FolhaFrequenciaStatus::APROVADA,
        ], true)) {
            throw new InvalidArgumentException('Apenas uma frequência finalizada ou aprovada pode ser preparada para exportação.');
        }

        $folha->loadMissing('competencia');
        $itens = FolhaFrequenciaItem::query()
            ->whereHas('servidorFolha', fn ($query) => $query->where('folha_frequencia_id', $folha->id))
            ->where('gera_efeito_financeiro', true)
            ->with('servidorFolha.servidor')
            ->lockForUpdate()
            ->get();

        return $itens->map(fn (FolhaFrequenciaItem $item): ProjecaoExportacaoFolha => $this->projetarItem($folha, $item));
    }

    /** @return Collection<int, ProjecaoExportacaoFolha> */
    public function projetarAprovadas(Competencia $competencia): Collection
    {
        return FolhaFrequencia::query()
            ->where('competencia_id', $competencia->id)
            ->where('status', FolhaFrequenciaStatus::APROVADA)
            ->lockForUpdate()
            ->get()
            ->flatMap(fn (FolhaFrequencia $folha): Collection => $this->projetar($folha))
            ->values();
    }

    private function projetarItem(FolhaFrequencia $folha, FolhaFrequenciaItem $item): ProjecaoExportacaoFolha
    {
        $servidorFolha = $item->servidorFolha;

        if (! $item->regra_validada_snapshot) {
            throw new InvalidArgumentException(
                "O item {$item->codigo_evento} de {$servidorFolha->nome} não possui regra homologada para exportação."
            );
        }

        if ($item->unidade_lancamento !== UnidadeLancamento::VALOR) {
            throw new InvalidArgumentException(
                "O item {$item->codigo_evento} de {$servidorFolha->nome} usa a unidade ".
                "{$item->unidade_lancamento->label()}, ainda sem conversão homologada para o TXT."
            );
        }

        if ($item->valor === null) {
            throw new InvalidArgumentException(
                "O item {$item->codigo_evento} de {$servidorFolha->nome} não possui valor para exportação."
            );
        }

        if (strlen($item->codigo_evento) > 10) {
            throw new InvalidArgumentException(
                "O código do item {$item->codigo_evento} excede o limite de 10 caracteres do TXT."
            );
        }

        if (empty($servidorFolha->matricula) || strlen($servidorFolha->matricula) > 14) {
            throw new InvalidArgumentException(
                "A matrícula de {$servidorFolha->nome} é inválida para o TXT."
            );
        }

        if ($servidorFolha->servidor && ! $servidorFolha->servidor->ativo) {
            throw new InvalidArgumentException(
                "O servidor {$servidorFolha->nome} está inativo e bloqueia a preparação da exportação."
            );
        }

        $atributos = [
            'folha_frequencia_id' => $folha->id,
            'competencia_id' => $folha->competencia_id,
            'rodada_conferencia' => $folha->rodada_conferencia,
            'servidor_id' => $servidorFolha->servidor_id,
            'codigo_evento' => $item->codigo_evento,
            'matricula' => $servidorFolha->matricula,
            'valor' => $item->valor,
            'status' => ProjecaoExportacaoStatus::PRONTA,
        ];

        $projecao = ProjecaoExportacaoFolha::firstOrCreate(
            ['folha_frequencia_item_id' => $item->id],
            $atributos,
        );

        if (! $projecao->wasRecentlyCreated) {
            $camposImutaveis = array_values(array_diff(array_keys($atributos), ['status']));
            if ($projecao->only($camposImutaveis) !== (new ProjecaoExportacaoFolha($atributos))->only($camposImutaveis)) {
                throw new InvalidArgumentException(
                    "A projeção do item {$item->codigo_evento} diverge do snapshot aprovado. A exportação foi bloqueada."
                );
            }
        }

        return $projecao;
    }
}
