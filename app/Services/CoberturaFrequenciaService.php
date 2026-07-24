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
        $servidoresPorSetor = $this->contarServidoresElegiveis($competencia, $setoresAtivos);
        $folhas = FolhaFrequencia::where('competencia_id', $competencia->id)
            ->with(['finalizadoPor', 'conferidoPor'])->withCount('servidores')->get()->keyBy('setor_id');

        return $servidoresPorSetor->map(function (int $total, int $setorId) use ($setoresAtivos, $folhas): array {
            /** @var FolhaFrequencia|null $folha */
            $folha = $folhas->get($setorId);

            if (! $folha) {
                return [
                    'setor' => $setoresAtivos->get($setorId),
                    'servidores_esperados' => $total,
                    'folha' => null,
                    'situacao' => 'NAO_INICIADA',
                    'label' => 'Não iniciada',
                    'cor' => 'secondary',
                    'bloqueia_fechamento' => true,
                ];
            }

            return [
                'setor' => $setoresAtivos->get($setorId),
                'servidores_esperados' => $total,
                'folha' => $folha,
                'situacao' => $folha->status->value,
                'label' => $folha->status->label(),
                'cor' => $folha->status->cor(),
                'bloqueia_fechamento' => $folha->status !== FolhaFrequenciaStatus::APROVADA,
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
            'pendencias' => $cobertura->where('bloqueia_fechamento', true)->count(),
            'pronta_para_fechar' => $cobertura->where('bloqueia_fechamento', true)->isEmpty(),
        ];
    }

    /** @param Collection<int, Setor> $setoresAtivos */
    private function contarServidoresElegiveis(Competencia $competencia, Collection $setoresAtivos): Collection
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
            ->map(fn (Servidor $servidor): int => $servidor->lotacoes->first()?->setor_id ?? $servidor->setor_id)
            ->filter(fn (int $setorId): bool => $setoresAtivos->has($setorId))
            ->countBy();
    }
}
