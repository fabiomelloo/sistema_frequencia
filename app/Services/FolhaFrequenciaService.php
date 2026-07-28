<?php

namespace App\Services;

use App\Enums\ConferenciaServidorStatus;
use App\Enums\EvidenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoOcorrenciaFrequencia;
use App\Models\Competencia;
use App\Models\ConferenciaFrequenciaServidor;
use App\Models\EvidenciaDocumental;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Models\User;
use App\Models\VinculoFuncional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FolhaFrequenciaService
{
    public function __construct(
        private readonly FolhaFrequenciaItemService $itemService,
        private readonly CoberturaFrequenciaService $coberturaService,
        private readonly ProjecaoExportacaoFolhaService $projecaoExportacaoService,
    ) {}

    public function criar(Competencia $competencia, User $user): FolhaFrequencia
    {
        if (! $competencia->estaAberta()) {
            throw new InvalidArgumentException('A competência está fechada.');
        }

        return DB::transaction(function () use ($competencia, $user): FolhaFrequencia {
            $folha = FolhaFrequencia::firstOrCreate(
                ['setor_id' => $user->setor_id, 'competencia_id' => $competencia->id],
                ['status' => FolhaFrequenciaStatus::RASCUNHO, 'criado_por_id' => $user->id],
            );

            if ($folha->wasRecentlyCreated) {
                foreach ($this->servidoresElegiveis($competencia, $user->setor_id) as $servidor) {
                    $vinculo = $this->vinculoDeReferencia($servidor, $competencia);
                    $designacoes = $this->designacoesDoPeriodo($servidor, $competencia);
                    $itemServidor = $folha->servidores()->create([
                        'servidor_id' => $servidor->id,
                        'vinculo_funcional_id' => $vinculo?->id,
                        'matricula' => $vinculo?->matricula ?? $servidor->matricula,
                        'nome' => $servidor->nome,
                        'cargo' => $vinculo?->cargo ?? $servidor->cargo,
                        'vinculo' => $vinculo?->tipo_vinculo?->value ?? $servidor->vinculo?->value,
                        'carga_horaria' => $vinculo?->carga_horaria ?? $servidor->carga_horaria,
                        'designacoes_snapshot' => $designacoes->values()->all(),
                        'mudanca_funcional_no_periodo' => $this->possuiMudancaFuncional($servidor, $competencia),
                        'status' => FrequenciaServidorStatus::PENDENTE,
                    ]);

                    foreach ($this->vantagensDoPeriodo($servidor, $competencia) as $vantagem) {
                        $itemServidor->itens()->create($this->itemService->snapshotEvento($vantagem->evento) + [
                            'evento_id' => $vantagem->evento_id,
                            'vantagem_funcional_id' => $vantagem->id,
                            'percentual' => $vantagem->percentual,
                            'valor' => $vantagem->valor,
                            'quantidade' => $vantagem->quantidade,
                            'nivel' => $vantagem->nivel,
                            'texto' => $vantagem->texto,
                            'observacao' => $vantagem->observacao,
                            'referencia_documento' => $vantagem->referencia_documento,
                            'vigencia_inicio' => $vantagem->data_inicio,
                            'vigencia_fim' => $vantagem->data_fim,
                            'editavel_pelo_setor' => false,
                        ]);
                    }
                }
            }

            return $folha;
        });
    }

    public function atualizarServidor(FolhaFrequenciaServidor $item, array $dados, User $user): FolhaFrequenciaServidor
    {
        $folha = $item->folha;
        if (! $folha->editavelPeloSetor() || ! $folha->competencia->estaAberta()) {
            throw new InvalidArgumentException('A folha não está aberta para alterações.');
        }

        $status = FrequenciaServidorStatus::from($dados['status']);
        $possuiFalta = $this->possuiFalta($item);

        if ($status === FrequenciaServidorStatus::COM_FALTAS && ! $possuiFalta) {
            throw new InvalidArgumentException('Registre ao menos uma ocorrência do tipo Falta antes de marcar COM FALTAS.');
        }
        if ($status === FrequenciaServidorStatus::INTEGRAL && $possuiFalta) {
            throw new InvalidArgumentException('Este servidor possui falta registrada e não pode ser marcado com frequência integral.');
        }

        $item->update([
            'status' => $status,
            'observacao_geral' => $dados['observacao_geral'] ?? null,
            'atualizado_por_id' => $user->id,
            'preenchida_em' => $status === FrequenciaServidorStatus::PENDENTE ? null : now(),
        ]);

        return $item->refresh();
    }

    public function finalizar(FolhaFrequencia $folha, User $user): FolhaFrequencia
    {
        return DB::transaction(function () use ($folha, $user): FolhaFrequencia {
            $folha = FolhaFrequencia::query()->with('competencia')->lockForUpdate()->findOrFail($folha->id);
            if (! $folha->editavelPeloSetor() || ! $folha->competencia->estaAberta()) {
                throw new InvalidArgumentException('A folha não está aberta para finalização.');
            }

            if (! $folha->servidores()->exists()) {
                throw new InvalidArgumentException('A folha não possui servidores para finalização.');
            }

            if ($folha->servidores()->where('status', FrequenciaServidorStatus::PENDENTE)->exists()) {
                throw new InvalidArgumentException('Ainda existem servidores com frequência pendente.');
            }

            $this->garantirPopulacaoIntegral($folha);

            foreach ($folha->servidores()->with('folha.competencia')->get() as $item) {
                $possuiFalta = $this->possuiFalta($item);
                if ($item->status === FrequenciaServidorStatus::COM_FALTAS && ! $possuiFalta) {
                    throw new InvalidArgumentException("{$item->nome} está marcado com faltas, mas não possui os dias registrados.");
                }
                if ($item->status === FrequenciaServidorStatus::INTEGRAL && $possuiFalta) {
                    throw new InvalidArgumentException("{$item->nome} possui falta registrada e não pode constar como frequência integral.");
                }
            }

            $this->garantirEvidenciasDaFolha($folha);

            $folha->update([
                'status' => FolhaFrequenciaStatus::FINALIZADA,
                'rodada_conferencia' => $folha->rodada_conferencia + 1,
                'finalizado_por_id' => $user->id,
                'finalizada_em' => now(),
                'conferido_por_id' => null,
                'conferida_em' => null,
                'motivo_devolucao' => null,
            ]);

            return $folha->refresh();
        });
    }

    public function reabrir(FolhaFrequencia $folha): FolhaFrequencia
    {
        if (! $folha->estaFinalizada()) {
            throw new InvalidArgumentException('Apenas uma folha aguardando conferência pode ser reaberta pelo setor.');
        }

        if (! $folha->competencia->estaAberta()) {
            throw new InvalidArgumentException('A competência está fechada e a folha não pode ser reaberta.');
        }

        $folha->update([
            'status' => FolhaFrequenciaStatus::RASCUNHO,
            'finalizado_por_id' => null,
            'finalizada_em' => null,
        ]);

        return $folha->refresh();
    }

    public function aprovar(FolhaFrequencia $folha, User $user): FolhaFrequencia
    {
        return DB::transaction(function () use ($folha, $user): FolhaFrequencia {
            $folha = FolhaFrequencia::query()->lockForUpdate()->findOrFail($folha->id);
            if (! $folha->estaFinalizada()) {
                throw new InvalidArgumentException('Apenas uma folha aguardando conferência pode ser aprovada.');
            }

            $this->garantirPopulacaoIntegral($folha);
            $this->garantirConferenciaCompleta($folha);
            $this->projecaoExportacaoService->projetar($folha);
            $folha->update([
                'status' => FolhaFrequenciaStatus::APROVADA,
                'conferido_por_id' => $user->id,
                'conferida_em' => now(),
                'motivo_devolucao' => null,
            ]);

            return $folha->refresh();
        });
    }

    public function devolver(FolhaFrequencia $folha, User $user, string $motivo): FolhaFrequencia
    {
        return DB::transaction(function () use ($folha, $user, $motivo): FolhaFrequencia {
            $folha = FolhaFrequencia::query()->with('competencia')->lockForUpdate()->findOrFail($folha->id);
            if (! $folha->estaFinalizada()) {
                throw new InvalidArgumentException('Apenas uma folha aguardando conferência pode ser devolvida.');
            }
            if (! $folha->competencia->estaAberta()) {
                throw new InvalidArgumentException('A competência está fechada e a folha não pode ser devolvida para edição.');
            }
            if (! $this->possuiDivergencia($folha)) {
                throw new InvalidArgumentException('Marque ao menos um servidor com divergência antes de devolver a frequência.');
            }

            $folha->update([
                'status' => FolhaFrequenciaStatus::DEVOLVIDA,
                'conferido_por_id' => $user->id,
                'conferida_em' => now(),
                'motivo_devolucao' => $motivo,
            ]);

            return $folha->refresh();
        });
    }

    public function conferirServidor(
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        ConferenciaServidorStatus $status,
        ?string $apontamento,
        User $usuario,
    ): ConferenciaFrequenciaServidor {
        return DB::transaction(function () use ($folha, $item, $status, $apontamento, $usuario): ConferenciaFrequenciaServidor {
            $folha = FolhaFrequencia::query()->lockForUpdate()->findOrFail($folha->id);
            if (! $folha->estaFinalizada()) {
                throw new InvalidArgumentException('A conferência por servidor só pode ser realizada enquanto a folha aguarda análise da Central.');
            }
            if ($item->folha_frequencia_id !== $folha->id) {
                throw new InvalidArgumentException('O servidor não pertence à frequência informada.');
            }
            if ($folha->rodada_conferencia < 1) {
                throw new InvalidArgumentException('A frequência ainda não possui uma rodada de conferência válida.');
            }
            if ($status === ConferenciaServidorStatus::DIVERGENTE && mb_strlen(trim((string) $apontamento)) < 10) {
                throw new InvalidArgumentException('Descreva objetivamente a divergência encontrada.');
            }
            if ($status === ConferenciaServidorStatus::CONFERIDO) {
                $this->garantirEvidenciasDaFolha($folha, $item, true);
            }

            return ConferenciaFrequenciaServidor::updateOrCreate(
                [
                    'folha_frequencia_servidor_id' => $item->id,
                    'rodada' => $folha->rodada_conferencia,
                ],
                [
                    'status' => $status,
                    'apontamento' => $status === ConferenciaServidorStatus::DIVERGENTE ? $apontamento : null,
                    'conferido_por_id' => $usuario->id,
                    'conferido_em' => now(),
                ],
            );
        });
    }

    /** @return Collection<int, Servidor> */
    private function servidoresElegiveis(Competencia $competencia, int $setorId): Collection
    {
        return Servidor::query()
            ->where(function ($query) use ($competencia, $setorId): void {
                $query->where('setor_id', $setorId)
                    ->orWhereHas('lotacoes', function ($lotacoes) use ($competencia, $setorId): void {
                        $lotacoes->where('setor_id', $setorId)
                            ->where('data_inicio', '<=', $competencia->fimPeriodo())
                            ->where(fn ($periodo) => $periodo->whereNull('data_fim')
                                ->orWhere('data_fim', '>=', $competencia->inicioPeriodo()));
                    });
            })
            ->with(['lotacoes', 'vinculosFuncionais', 'designacoesFuncionais', 'vantagensFuncionais.evento'])
            ->orderBy('nome')
            ->get()
            ->filter(fn (Servidor $servidor): bool => $servidor->estaAtivoNaCompetencia($competencia->referencia)
                && $servidor->setorNaCompetencia($competencia->referencia) === $setorId)
            ->values();
    }

    private function vinculoDeReferencia(Servidor $servidor, Competencia $competencia): ?VinculoFuncional
    {
        $inicio = $competencia->inicioPeriodo();
        $fim = $competencia->fimPeriodo();
        $sobrepostos = $servidor->vinculosFuncionais->filter(fn ($vinculo): bool => (! $vinculo->data_inicio || $vinculo->data_inicio->lte($fim))
            && (! $vinculo->data_fim || $vinculo->data_fim->gte($inicio))
        );

        return $sobrepostos->first(fn ($vinculo): bool => (! $vinculo->data_inicio || $vinculo->data_inicio->lte($fim))
            && (! $vinculo->data_fim || $vinculo->data_fim->gte($fim))
        ) ?? $sobrepostos->first();
    }

    private function designacoesDoPeriodo(Servidor $servidor, Competencia $competencia): Collection
    {
        $inicio = $competencia->inicioPeriodo();
        $fim = $competencia->fimPeriodo();

        return $servidor->designacoesFuncionais
            ->filter(fn ($designacao): bool => $designacao->data_inicio->lte($fim)
                && (! $designacao->data_fim || $designacao->data_fim->gte($inicio)))
            ->map(fn ($designacao): array => [
                'tipo' => $designacao->tipo->value,
                'nivel' => $designacao->nivel,
                'descricao' => $designacao->descricao,
                'data_inicio' => $designacao->data_inicio->toDateString(),
                'data_fim' => $designacao->data_fim?->toDateString(),
                'ato_referencia' => $designacao->ato_referencia,
            ]);
    }

    private function vantagensDoPeriodo(Servidor $servidor, Competencia $competencia): Collection
    {
        $inicio = $competencia->inicioPeriodo();
        $fim = $competencia->fimPeriodo();

        return $servidor->vantagensFuncionais
            ->filter(fn ($vantagem): bool => $vantagem->data_inicio->lte($fim)
                && (! $vantagem->data_fim || $vantagem->data_fim->gte($inicio))
                && $vantagem->evento?->ativo
                && $vantagem->evento?->regra_validada
                && $vantagem->evento?->origem_informacao === OrigemInformacaoItem::CADASTRO_FUNCIONAL);
    }

    private function possuiMudancaFuncional(Servidor $servidor, Competencia $competencia): bool
    {
        $inicio = $competencia->inicioPeriodo();
        $fim = $competencia->fimPeriodo();
        $colecoes = [
            $servidor->vinculosFuncionais,
            $servidor->designacoesFuncionais,
            $servidor->vantagensFuncionais,
        ];

        foreach ($colecoes as $registros) {
            foreach ($registros as $registro) {
                if (($registro->data_inicio && $registro->data_inicio->between($inicio, $fim))
                    || ($registro->data_fim && $registro->data_fim->between($inicio, $fim))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function possuiFalta(FolhaFrequenciaServidor $item): bool
    {
        return OcorrenciaFrequencia::where('servidor_id', $item->servidor_id)
            ->where('competencia_id', $item->folha->competencia_id)
            ->where('tipo', TipoOcorrenciaFrequencia::FALTA)
            ->exists();
    }

    private function garantirConferenciaCompleta(FolhaFrequencia $folha): void
    {
        $servidoresIds = $folha->servidores()->pluck('id');
        $total = $servidoresIds->count();
        $conferidos = ConferenciaFrequenciaServidor::query()
            ->whereIn('folha_frequencia_servidor_id', $servidoresIds)
            ->where('rodada', $folha->rodada_conferencia)
            ->where('status', ConferenciaServidorStatus::CONFERIDO)
            ->count();

        if ($total === 0 || $conferidos !== $total) {
            $pendentes = $total - $conferidos;
            throw new InvalidArgumentException("A aprovação está bloqueada: {$pendentes} servidor(es) ainda não foram conferidos sem divergência.");
        }

        $this->garantirEvidenciasDaFolha($folha, null, true);
    }

    private function garantirPopulacaoIntegral(FolhaFrequencia $folha): void
    {
        $divergencia = $this->coberturaService->divergenciaDaFolha($folha);

        if (! $divergencia['populacao_integral']) {
            $faltantes = count($divergencia['servidores_faltantes']);
            $excedentes = count($divergencia['servidores_excedentes']);

            throw new InvalidArgumentException(
                'A folha está desatualizada em relação à população da competência: '
                ."{$faltantes} servidor(es) faltante(s) e {$excedentes} excedente(s). "
                .'Atualize a relação antes de continuar.'
            );
        }
    }

    private function garantirEvidenciasDaFolha(
        FolhaFrequencia $folha,
        ?FolhaFrequenciaServidor $servidorFolha = null,
        bool $exigirAceitas = false,
    ): void {
        $linhas = $servidorFolha
            ? collect([$servidorFolha])
            : $folha->servidores()->get(['id', 'servidor_id', 'nome']);
        $linhasIds = $linhas->pluck('id');
        $servidoresIds = $linhas->pluck('servidor_id');

        $itemSemDocumento = FolhaFrequenciaItem::query()
            ->whereIn('folha_frequencia_servidor_id', $linhasIds)
            ->where('exige_documento', true)
            ->whereDoesntHave('evidencias')
            ->first();
        if ($itemSemDocumento) {
            throw new InvalidArgumentException("Anexe o documento obrigatório do item {$itemSemDocumento->codigo_evento} antes de prosseguir.");
        }

        $ocorrenciaSemDocumento = OcorrenciaFrequencia::query()
            ->where('setor_id', $folha->setor_id)
            ->where('competencia_id', $folha->competencia_id)
            ->whereIn('servidor_id', $servidoresIds)
            ->where('possui_comprovacao', true)
            ->whereDoesntHave('evidencias')
            ->with('servidor')
            ->first();
        if ($ocorrenciaSemDocumento) {
            throw new InvalidArgumentException("Anexe a comprovação informada na ocorrência de {$ocorrenciaSemDocumento->servidor->nome} antes de prosseguir.");
        }

        if (! $exigirAceitas) {
            return;
        }

        $itensIds = FolhaFrequenciaItem::query()
            ->whereIn('folha_frequencia_servidor_id', $linhasIds)
            ->pluck('id');
        $ocorrenciasIds = OcorrenciaFrequencia::query()
            ->where('setor_id', $folha->setor_id)
            ->where('competencia_id', $folha->competencia_id)
            ->whereIn('servidor_id', $servidoresIds)
            ->pluck('id');

        $evidenciaPendente = EvidenciaDocumental::query()
            ->where(function ($query) use ($itensIds, $ocorrenciasIds): void {
                $query->whereIn('folha_frequencia_item_id', $itensIds)
                    ->orWhereIn('ocorrencia_frequencia_id', $ocorrenciasIds);
            })
            ->where('status', '!=', EvidenciaStatus::ACEITA)
            ->first();
        if ($evidenciaPendente) {
            throw new InvalidArgumentException("O documento {$evidenciaPendente->nome_original} ainda não foi aceito pela Central.");
        }
    }

    private function possuiDivergencia(FolhaFrequencia $folha): bool
    {
        return ConferenciaFrequenciaServidor::query()
            ->whereIn('folha_frequencia_servidor_id', $folha->servidores()->select('id'))
            ->where('rodada', $folha->rodada_conferencia)
            ->where('status', ConferenciaServidorStatus::DIVERGENTE)
            ->exists();
    }
}
