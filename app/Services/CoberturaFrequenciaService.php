<?php

namespace App\Services;

use App\Enums\FolhaFrequenciaStatus;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\Servidor;
use App\Models\Setor;
use Illuminate\Support\Collection;

class CoberturaFrequenciaService
{
    /**
     * @return Collection<int, array{
     *     setor: Setor,
     *     servidores_esperados: int,
     *     servidores_na_folha: int,
     *     servidores_faltantes: array<int, int>,
     *     servidores_excedentes: array<int, int>,
     *     populacao_integral: bool,
     *     divergencia_populacao: bool,
     *     folha: FolhaFrequencia|null,
     *     situacao: string,
     *     label: string,
     *     cor: string,
     *     bloqueia_fechamento: bool
     * }>
     */
    public function porCompetencia(Competencia $competencia): Collection
    {
        $setoresAtivos = Setor::where('ativo', true)->orderBy('nome')->get()->keyBy('id');
        $servidoresPorSetor = $this->servidoresElegiveisPorSetor($competencia, $setoresAtivos);
        $folhas = FolhaFrequencia::where('competencia_id', $competencia->id)
            ->with(['finalizadoPor', 'conferidoPor', 'servidores'])
            ->withCount('servidores')
            ->get()
            ->keyBy('setor_id');
        $setoresComCobertura = $servidoresPorSetor->keys()
            ->merge($folhas->filter(fn (FolhaFrequencia $folha): bool => $folha->servidores_count > 0)->keys())
            ->filter(fn (int $setorId): bool => $setoresAtivos->has($setorId))
            ->unique();

        return $setoresComCobertura->map(function (int $setorId) use ($setoresAtivos, $servidoresPorSetor, $folhas): array {
            /** @var FolhaFrequencia|null $folha */
            $folha = $folhas->get($setorId);
            $esperados = $servidoresPorSetor->get($setorId, collect());
            $divergencia = $this->compararPopulacao($esperados, $folha);

            if (! $folha) {
                return [
                    'setor' => $setoresAtivos->get($setorId),
                    'servidores_esperados' => $esperados->count(),
                    'servidores_na_folha' => 0,
                    'servidores_faltantes' => $divergencia['servidores_faltantes'],
                    'servidores_excedentes' => [],
                    'populacao_integral' => false,
                    'divergencia_populacao' => false,
                    'folha' => null,
                    'situacao' => 'NAO_INICIADA',
                    'label' => 'Não iniciada',
                    'cor' => 'secondary',
                    'bloqueia_fechamento' => true,
                ];
            }

            return [
                'setor' => $setoresAtivos->get($setorId),
                'servidores_esperados' => $esperados->count(),
                'servidores_na_folha' => $divergencia['servidores_na_folha'],
                'servidores_faltantes' => $divergencia['servidores_faltantes'],
                'servidores_excedentes' => $divergencia['servidores_excedentes'],
                'populacao_integral' => $divergencia['populacao_integral'],
                'divergencia_populacao' => ! $divergencia['populacao_integral'],
                'folha' => $folha,
                'situacao' => $folha->status->value,
                'label' => $folha->status->label(),
                'cor' => $folha->status->cor(),
                'bloqueia_fechamento' => $folha->status !== FolhaFrequenciaStatus::APROVADA
                    || ! $divergencia['populacao_integral'],
            ];
        })->sortBy(fn (array $item): string => $item['setor']->nome)->values();
    }

    /** @return array<string, int|bool> */
    public function resumo(Competencia $competencia, ?Collection $cobertura = null): array
    {
        $cobertura ??= $this->porCompetencia($competencia);

        return [
            'setores_esperados' => $cobertura->count(),
            'aprovadas' => $cobertura->where('situacao', FolhaFrequenciaStatus::APROVADA->value)->count(),
            'aguardando' => $cobertura->where('situacao', FolhaFrequenciaStatus::FINALIZADA->value)->count(),
            'devolvidas' => $cobertura->where('situacao', FolhaFrequenciaStatus::DEVOLVIDA->value)->count(),
            'em_preenchimento' => $cobertura->where('situacao', FolhaFrequenciaStatus::RASCUNHO->value)->count(),
            'nao_iniciadas' => $cobertura->where('situacao', 'NAO_INICIADA')->count(),
            'divergencias_populacao' => $cobertura->where('divergencia_populacao', true)->count(),
            'servidores_faltantes' => $cobertura->sum(fn (array $item): int => count($item['servidores_faltantes'])),
            'servidores_excedentes' => $cobertura->sum(fn (array $item): int => count($item['servidores_excedentes'])),
            'pendencias' => $cobertura->where('bloqueia_fechamento', true)->count(),
            'pronta_para_fechar' => $cobertura->where('bloqueia_fechamento', true)->isEmpty(),
        ];
    }

    /**
     * @return array{
     *     servidores_esperados: int,
     *     servidores_na_folha: int,
     *     servidores_faltantes: array<int, int>,
     *     servidores_excedentes: array<int, int>,
     *     populacao_integral: bool
     * }
     */
    public function divergenciaDaFolha(FolhaFrequencia $folha): array
    {
        $folha->loadMissing(['competencia', 'servidores']);
        $setoresAtivos = Setor::where('ativo', true)->get()->keyBy('id');
        $esperados = $this->servidoresElegiveisPorSetor($folha->competencia, $setoresAtivos)
            ->get($folha->setor_id, collect());

        return $this->compararPopulacao($esperados, $folha);
    }

    /** @param Collection<int, Setor> $setoresAtivos */
    private function servidoresElegiveisPorSetor(Competencia $competencia, Collection $setoresAtivos): Collection
    {
        $servidores = Servidor::query()
            ->where(fn ($query) => $query->where('ativo', true)
                ->orWhere('data_desligamento', '>=', $competencia->inicioPeriodo()))
            ->where(fn ($query) => $query->whereNull('data_admissao')
                ->orWhere('data_admissao', '<=', $competencia->fimPeriodo()))
            ->with(['lotacoes' => fn ($query) => $query
                ->where('data_inicio', '<=', $competencia->fimPeriodo())
                ->where(fn ($periodo) => $periodo->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $competencia->inicioPeriodo()))
                ->orderByDesc('data_inicio')])
            ->get();

        return $servidores
            ->groupBy(fn (Servidor $servidor): int => $servidor->lotacoes->first()?->setor_id ?? $servidor->setor_id)
            ->filter(fn (Collection $servidores, int $setorId): bool => $setoresAtivos->has($setorId))
            ->map(fn (Collection $servidores): Collection => $servidores
                ->pluck('id')
                ->map(fn (int $servidorId): int => $servidorId)
                ->unique()
                ->values());
    }

    /**
     * @param  Collection<int, int>  $esperados
     * @return array{
     *     servidores_esperados: int,
     *     servidores_na_folha: int,
     *     servidores_faltantes: array<int, int>,
     *     servidores_excedentes: array<int, int>,
     *     populacao_integral: bool
     * }
     */
    private function compararPopulacao(Collection $esperados, ?FolhaFrequencia $folha): array
    {
        $materializados = $folha
            ? $folha->servidores->pluck('servidor_id')
                ->map(fn (int $servidorId): int => $servidorId)
                ->unique()
                ->values()
            : collect();
        $faltantes = $esperados->diff($materializados)->values();
        $excedentes = $materializados->diff($esperados)->values();

        return [
            'servidores_esperados' => $esperados->count(),
            'servidores_na_folha' => $materializados->count(),
            'servidores_faltantes' => $faltantes->all(),
            'servidores_excedentes' => $excedentes->all(),
            'populacao_integral' => $folha !== null && $faltantes->isEmpty() && $excedentes->isEmpty(),
        ];
    }
}
