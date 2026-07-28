<?php

namespace App\Services;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\LancamentoSetorial;
use Illuminate\Support\Facades\Auth;

class CompetenciaService
{
    /**
     * Abre uma nova competência.
     */
    public function abrir(string $referencia, ?string $dataLimite = null): Competencia
    {
        $existente = Competencia::buscarPorReferencia($referencia);

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

            if ($exportados > 0 || $solicitados > 0) {
                throw new \InvalidArgumentException(
                    "Não é possível reabrir a competência {$referencia}: existem {$exportados} lançamento(s) exportado(s) ".
                    "e {$solicitados} solicitação(ões) de estorno pendente(s). ".
                    'Solicite os estornos necessários e aguarde a conclusão pela Central antes de reabrir.'
                );
            }

            $existente->status = CompetenciaStatus::ABERTA;
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
    }

    /**
     * Fecha uma competência.
     */
    public function fechar(Competencia $competencia): Competencia
    {
        if ($competencia->estaFechada()) {
            throw new \InvalidArgumentException("A competência {$competencia->referencia} já está fechada.");
        }

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

        $competencia->status = CompetenciaStatus::FECHADA;
        $competencia->fechada_por = Auth::id() ?? (\App\Models\User::firstWhere('email', 'admin@example.com')?->id ?? \App\Models\User::first()?->id);
        $competencia->fechada_em = now();
        $competencia->save();

        return $competencia;
    }

    /**
     * Retorna estatísticas dos lançamentos por status para uma competência.
     */
    public function estatisticas(string $referencia): array
    {
        $contadores = [];
        foreach (LancamentoStatus::cases() as $status) {
            $contadores[$status->value] = LancamentoSetorial::where('competencia', $referencia)
                ->where('status', $status->value)
                ->count();
        }

        return $contadores;
    }

    /**
     * Verifica lançamentos com SLA ultrapassado e gera alertas.
     */
    public function verificarSla(): array
    {
        $slaDias = Configuracao::getInt('sla_dias_conferencia', 5);

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
