<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\EventoFolha;
use App\Enums\LancamentoStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        if ($user->isCentral()) {
            return $this->dashboardCentral();
        }

        return $this->dashboardSetorial();
    }

    private function dashboardSetorial(): View
    {
        $user = auth()->user();
        $setorId = $user->setor_id;
        $competenciaAtual = now()->format('Y-m');

        $contadores = [
            'pendentes' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('status', LancamentoStatus::PENDENTE)->count(),
            'conferidos' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('status', LancamentoStatus::CONFERIDO)->count(),
            'rejeitados' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('status', LancamentoStatus::REJEITADO)->count(),
            'exportados' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('status', LancamentoStatus::EXPORTADO)->count(),
        ];

        $contadoresMes = [
            'total' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('competencia', $competenciaAtual)->count(),
            'pendentes' => LancamentoSetorial::where('setor_origem_id', $setorId)
                ->where('competencia', $competenciaAtual)
                ->where('status', LancamentoStatus::PENDENTE)->count(),
        ];

        $ultimosLancamentos = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->with(['servidor', 'evento'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $rejeitadosRecentes = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->where('status', LancamentoStatus::REJEITADO)
            ->with(['servidor', 'evento', 'validador'])
            ->orderBy('validated_at', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard.setorial', [
            'contadores' => $contadores,
            'contadoresMes' => $contadoresMes,
            'ultimosLancamentos' => $ultimosLancamentos,
            'rejeitadosRecentes' => $rejeitadosRecentes,
            'competenciaAtual' => $competenciaAtual,
            'setor' => $user->setor,
        ]);
    }

    private function dashboardCentral(): View
    {
        $competenciaAtual = now()->format('Y-m');

        $contadores = [
            'pendentes' => LancamentoSetorial::where('status', LancamentoStatus::PENDENTE)->count(),
            'conferidos' => LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO)->count(),
            'rejeitados' => LancamentoSetorial::where('status', LancamentoStatus::REJEITADO)->count(),
            'exportados' => LancamentoSetorial::where('status', LancamentoStatus::EXPORTADO)->count(),
        ];

        // Pendentes por setor
        $pendentesPorSetor = LancamentoSetorial::where('status', LancamentoStatus::PENDENTE)
            ->selectRaw('setor_origem_id, COUNT(*) as total')
            ->groupBy('setor_origem_id')
            ->with('setorOrigem')
            ->get()
            ->map(fn ($item) => [
                'setor' => $item->setorOrigem->sigla ?? $item->setorOrigem->nome,
                'total' => $item->total,
            ]);

        // Lançamentos por competência (últimos 6 meses)
        $lancamentosPorCompetencia = LancamentoSetorial::selectRaw('competencia, status, COUNT(*) as total')
            ->whereNotNull('competencia')
            ->groupBy('competencia', 'status')
            ->orderBy('competencia', 'desc')
            ->limit(30)
            ->get();

        // Pendentes mais antigos (SLA)
        $pendentesAntigos = LancamentoSetorial::where('status', LancamentoStatus::PENDENTE)
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        $totalServidores = Servidor::where('ativo', true)->count();
        $totalSetores = Setor::where('ativo', true)->count();
        $totalEventos = EventoFolha::where('ativo', true)->count();

        return view('dashboard.central', [
            'contadores' => $contadores,
            'pendentesPorSetor' => $pendentesPorSetor,
            'lancamentosPorCompetencia' => $lancamentosPorCompetencia,
            'pendentesAntigos' => $pendentesAntigos,
            'totalServidores' => $totalServidores,
            'totalSetores' => $totalSetores,
            'totalEventos' => $totalEventos,
            'competenciaAtual' => $competenciaAtual,
        ]);
    }
}
