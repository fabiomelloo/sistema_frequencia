<?php

namespace App\Services;

use App\Models\Competencia;
use App\Models\LancamentoSetorial;
use App\Models\Configuracao;
use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
            // Regra #13: alertar se existem lançamentos exportados
            $exportados = LancamentoSetorial::where('competencia', $referencia)
                ->where('status', LancamentoStatus::EXPORTADO->value)
                ->count();

            if ($exportados > 0) {
                throw new \InvalidArgumentException(
                    "A competência {$referencia} possui {$exportados} lançamento(s) já exportado(s). " .
                    "Reabrir pode causar inconsistências com a folha de pagamento. " .
                    "Estorne os lançamentos exportados antes de reabrir."
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
                "Não é possível fechar a competência {$competencia->referencia}. " .
                "Existem {$pendentes} lançamento(s) pendente(s) de conferência."
            );
        }

        $estornados = LancamentoSetorial::where('competencia', $competencia->referencia)
            ->where('status', LancamentoStatus::ESTORNADO->value)
            ->count();

        if ($estornados > 0) {
            throw new \InvalidArgumentException(
                "Não é possível fechar a competência {$competencia->referencia}. " .
                "Existem {$estornados} lançamento(s) estornado(s) aguardando reprocessamento. " .
                "Resolva os estornos antes de fechar."
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
