<?php

namespace App\Services;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Enums\ProjecaoExportacaoStatus;
use App\Enums\UserRole;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\LancamentoSetorial;
use App\Models\ProjecaoExportacaoFolha;
use App\Models\User;
use App\Support\SystemDefaults;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompetenciaService
{
    public function __construct(
        private readonly ProjecaoExportacaoFolhaService $projecaoExportacaoService,
    ) {}

    /**
     * Abre uma nova competência.
     */
    public function abrir(string $referencia, ?string $dataLimite = null): Competencia
    {
        return DB::transaction(function () use ($referencia, $dataLimite): Competencia {
            $existente = Competencia::query()->where('referencia', $referencia)->lockForUpdate()->first();

            if ($existente && $existente->estaAberta()) {
                throw new \InvalidArgumentException("A competência {$referencia} já está aberta.");
            }

            if ($existente && $existente->estaFechada()) {
                $impedimentos = LancamentoSetorial::where('competencia', $referencia)
                    ->whereIn('status', [
                        LancamentoStatus::EXPORTADO->value,
                        LancamentoStatus::ESTORNO_SOLICITADO->value,
                    ])
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');
                $exportados = (int) ($impedimentos[LancamentoStatus::EXPORTADO->value] ?? 0);
                $solicitados = (int) ($impedimentos[LancamentoStatus::ESTORNO_SOLICITADO->value] ?? 0);
                $projecoesExportadas = ProjecaoExportacaoFolha::where('competencia_id', $existente->id)
                    ->where('status', ProjecaoExportacaoStatus::EXPORTADA)
                    ->count();

                if ($exportados > 0 || $solicitados > 0 || $projecoesExportadas > 0) {
                    throw new \InvalidArgumentException(
                        "Não é possível reabrir a competência {$referencia}: existem {$exportados} lançamento(s) legado(s) exportado(s), "
                        ."{$solicitados} solicitação(ões) de estorno pendente(s) e {$projecoesExportadas} item(ns) nativo(s) exportado(s). "
                        .'Conclua os estornos e a reconciliação da exportação antes de reabrir.'
                    );
                }

                $existente->status = CompetenciaStatus::ABERTA;
                if (! $existente->data_inicio || ! $existente->data_fim) {
                    [$inicio, $fim] = Competencia::periodoPadrao($referencia);
                    $existente->data_inicio = $inicio;
                    $existente->data_fim = $fim;
                }
                $existente->data_limite = $dataLimite;
                $existente->aberta_por = Auth::id();
                $existente->fechada_por = null;
                $existente->fechada_em = null;
                $existente->save();

                return $existente;
            }

            return Competencia::create([
                'referencia' => $referencia,
                'status' => CompetenciaStatus::ABERTA,
                'data_limite' => $dataLimite,
                'aberta_por' => Auth::id(),
                'fechada_por' => null,
                'fechada_em' => null,
            ]);
        });
    }

    /**
     * Fecha uma competência.
     */
    public function fechar(Competencia $competencia): Competencia
    {
        return DB::transaction(function () use ($competencia): Competencia {
            $competencia = Competencia::query()->lockForUpdate()->findOrFail($competencia->id);

            if ($competencia->estaFechada()) {
                throw new \InvalidArgumentException("A competência {$competencia->referencia} já está fechada.");
            }

            $this->projecaoExportacaoService->projetarAprovadas($competencia);

            $pendentes = LancamentoSetorial::where('competencia', $competencia->referencia)
                ->whereIn('status', [
                    LancamentoStatus::PENDENTE->value,
                    LancamentoStatus::CONFERIDO_SETORIAL->value,
                ])
                ->count();

            if ($pendentes > 0) {
                throw new \InvalidArgumentException(
                    "Não é possível fechar a competência {$competencia->referencia}. ".
                    "Existem {$pendentes} lançamento(s) pendente(s) de conferência."
                );
            }

            $estornosPendentes = LancamentoSetorial::where('competencia', $competencia->referencia)
                ->where('status', LancamentoStatus::ESTORNO_SOLICITADO->value)
                ->count();

            if ($estornosPendentes > 0) {
                throw new \InvalidArgumentException(
                    "Não é possível fechar a competência {$competencia->referencia}. ".
                    "Existem {$estornosPendentes} solicitação(ões) de estorno pendente(s). ".
                    'Conclua ou recuse as solicitações antes de fechar.'
                );
            }

            $cobertura = app(CoberturaFrequenciaService::class)->resumo($competencia);
            if (! $cobertura['pronta_para_fechar']) {
                $detalhes = array_filter([
                    $cobertura['nao_iniciadas'] > 0 ? "{$cobertura['nao_iniciadas']} não iniciada(s)" : null,
                    $cobertura['em_preenchimento'] > 0 ? "{$cobertura['em_preenchimento']} em preenchimento" : null,
                    $cobertura['aguardando'] > 0 ? "{$cobertura['aguardando']} aguardando conferência" : null,
                    $cobertura['devolvidas'] > 0 ? "{$cobertura['devolvidas']} devolvida(s)" : null,
                    ($cobertura['setores_inativos'] ?? 0) > 0 ? "{$cobertura['setores_inativos']} setor(es) inativo(s) com servidores elegíveis" : null,
                    $cobertura['divergencias_populacao'] > 0
                        ? "{$cobertura['divergencias_populacao']} folha(s) com divergência populacional "
                            ."({$cobertura['servidores_faltantes']} servidor(es) faltante(s), "
                            ."{$cobertura['servidores_excedentes']} excedente(s))"
                        : null,
                ]);

                $motivo = 'Pendências: '.implode(', ', $detalhes).'.';

                throw new \InvalidArgumentException(
                    "Não é possível fechar a competência {$competencia->referencia}. {$motivo} ".
                    'Todas as frequências mensais obrigatórias devem estar aprovadas.'
                );
            }

            $competencia->status = CompetenciaStatus::FECHADA;
            $emailSistema = Configuracao::get('email_usuario_sistema', 'admin@example.com');
            $competencia->fechada_por = Auth::id()
                ?? (User::firstWhere('email', $emailSistema)?->id
                    ?? User::where('role', UserRole::ADMIN)->first()?->id
                    ?? User::first()?->id);
            $competencia->fechada_em = now();
            $competencia->save();

            return $competencia;
        });
    }

    /**
     * Retorna estatísticas dos lançamentos por status para uma competência.
     */
    public function estatisticas(string $referencia): array
    {
        $cases = [];
        foreach (LancamentoStatus::cases() as $status) {
            $cases[] = "COUNT(CASE WHEN status = '{$status->value}' THEN 1 END) as `{$status->value}`";
        }

        $row = LancamentoSetorial::where('competencia', $referencia)
            ->selectRaw(implode(', ', $cases))
            ->first();

        $contadores = [];
        foreach (LancamentoStatus::cases() as $status) {
            $contadores[$status->value] = $row->{$status->value} ?? 0;
        }

        return $contadores;
    }

    /**
     * Verifica lançamentos com SLA ultrapassado e gera alertas.
     */
    public function verificarSla(): array
    {
        $slaDias = Configuracao::getInt('sla_dias_conferencia', SystemDefaults::SLA_DIAS_CONFERENCIA);

        $atrasados = LancamentoSetorial::whereIn('status', [
            LancamentoStatus::PENDENTE->value,
            LancamentoStatus::CONFERIDO_SETORIAL->value,
        ])
            ->where('created_at', '<=', now()->subDays($slaDias))
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->get();

        return [
            'total_atrasados' => $atrasados->count(),
            'lancamentos' => $atrasados,
            'sla_dias' => $slaDias,
        ];
    }
}
