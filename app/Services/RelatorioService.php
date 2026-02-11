<?php

namespace App\Services;

use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Enums\LancamentoStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RelatorioService
{
    /**
     * Resumo de uma competência: totais por setor, status, valores.
     */
    public function resumoCompetencia(string $competencia): array
    {
        $lancamentos = LancamentoSetorial::where('competencia', $competencia)
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->get();

        // Contadores por status
        $porStatus = [];
        foreach (LancamentoStatus::cases() as $status) {
            $porStatus[$status->value] = $lancamentos->where('status', $status)->count();
        }

        // Por setor
        $porSetor = $lancamentos->groupBy('setor_origem_id')->map(function ($grupo) {
            return [
                'setor' => $grupo->first()->setorOrigem->nome ?? 'N/A',
                'total' => $grupo->count(),
                'pendentes' => $grupo->where('status', LancamentoStatus::PENDENTE)->count(),
                'conferidos' => $grupo->where('status', LancamentoStatus::CONFERIDO)->count()
                    + $grupo->where('status', LancamentoStatus::CONFERIDO_SETORIAL)->count(),
                'rejeitados' => $grupo->where('status', LancamentoStatus::REJEITADO)->count(),
                'exportados' => $grupo->where('status', LancamentoStatus::EXPORTADO)->count(),
                'valor_total' => $grupo->sum('valor') + $grupo->sum('valor_gratificacao')
                    + $grupo->sum('adicional_turno') + $grupo->sum('adicional_noturno'),
            ];
        })->values()->toArray();

        // Por evento
        $porEvento = $lancamentos->groupBy('evento_id')->map(function ($grupo) {
            return [
                'evento' => $grupo->first()->evento->descricao ?? 'N/A',
                'codigo' => $grupo->first()->evento->codigo_evento ?? '',
                'total' => $grupo->count(),
                'valor_total' => $grupo->sum('valor') + $grupo->sum('valor_gratificacao'),
            ];
        })->values()->toArray();

        return [
            'competencia' => $competencia,
            'total_lancamentos' => $lancamentos->count(),
            'por_status' => $porStatus,
            'por_setor' => $porSetor,
            'por_evento' => $porEvento,
            'valor_geral' => $lancamentos->sum('valor') + $lancamentos->sum('valor_gratificacao')
                + $lancamentos->sum('adicional_turno') + $lancamentos->sum('adicional_noturno'),
        ];
    }

    /**
     * Comparativo entre competências.
     */
    public function comparativo(string $competenciaA, string $competenciaB): array
    {
        return [
            'a' => $this->resumoCompetencia($competenciaA),
            'b' => $this->resumoCompetencia($competenciaB),
        ];
    }

    /**
     * Folha-espelho: todos os eventos de um servidor em uma competência.
     */
    public function folhaEspelho(int $servidorId, string $competencia): array
    {
        $servidor = Servidor::with('setor')->findOrFail($servidorId);
        
        $lancamentos = LancamentoSetorial::where('servidor_id', $servidorId)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [LancamentoStatus::REJEITADO->value, LancamentoStatus::ESTORNADO->value])
            ->with(['evento', 'setorOrigem', 'validador'])
            ->orderBy('created_at')
            ->get();

        return [
            'servidor' => $servidor,
            'competencia' => $competencia,
            'lancamentos' => $lancamentos,
            'total_dias' => $lancamentos->sum('dias_trabalhados'),
            'total_valor' => $lancamentos->sum('valor') + $lancamentos->sum('valor_gratificacao')
                + $lancamentos->sum('adicional_turno') + $lancamentos->sum('adicional_noturno'),
        ];
    }

    /**
     * Gera CSV string para exportação Excel.
     */
    public function gerarCsv(string $competencia): string
    {
        $lancamentos = LancamentoSetorial::where('competencia', $competencia)
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->orderBy('setor_origem_id')
            ->orderBy('servidor_id')
            ->get();

        $csv = "Competência;Setor;Matrícula;Servidor;Evento;Código;Dias;Valor;Status\n";

        foreach ($lancamentos as $l) {
            $valor = $l->valor ?? $l->valor_gratificacao ?? $l->adicional_turno ?? $l->adicional_noturno ?? 0;
            $csv .= implode(';', [
                $l->competencia,
                $l->setorOrigem->nome ?? '',
                $l->servidor->matricula ?? '',
                $l->servidor->nome ?? '',
                $l->evento->descricao ?? '',
                $l->evento->codigo_evento ?? '',
                $l->dias_trabalhados ?? '',
                number_format($valor, 2, ',', '.'),
                $l->status->value ?? $l->status,
            ]) . "\n";
        }

        return $csv;
    }
}
