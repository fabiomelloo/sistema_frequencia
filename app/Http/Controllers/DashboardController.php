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

        // Otimização: Agregação em uma única query
        $stats = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->selectRaw("
                COUNT(CASE WHEN status = 'PENDENTE' THEN 1 END) as pendentes,
                COUNT(CASE WHEN status = 'CONFERIDO_SETORIAL' THEN 1 END) as conferidos_setorial,
                COUNT(CASE WHEN status = 'CONFERIDO' THEN 1 END) as conferidos,
                COUNT(CASE WHEN status = 'REJEITADO' THEN 1 END) as rejeitados,
                COUNT(CASE WHEN status = 'EXPORTADO' THEN 1 END) as exportados
            ")
            ->first();

        // Agrupa conferidos (Setorial + Central) para visualização simplificada, se desejado,
        // mas mantendo chaves originais para compatibilidade com a view
        $contadores = [
            'pendentes' => $stats->pendentes,
            'conferidos' => $stats->conferidos + $stats->conferidos_setorial, // Soma ambos os conferidos
            'rejeitados' => $stats->rejeitados,
            'exportados' => $stats->exportados,
        ];

        $statsMes = LancamentoSetorial::where('setor_origem_id', $setorId)
            ->where('competencia', $competenciaAtual)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'PENDENTE' THEN 1 END) as pendentes
            ")
            ->first();

        $contadoresMes = [
            'total' => $statsMes->total,
            'pendentes' => $statsMes->pendentes,
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

        // Otimização: Agregação em uma única query
        $stats = LancamentoSetorial::selectRaw("
                COUNT(CASE WHEN status = 'PENDENTE' THEN 1 END) as pendentes,
                COUNT(CASE WHEN status = 'CONFERIDO' THEN 1 END) as conferidos,
                COUNT(CASE WHEN status = 'REJEITADO' THEN 1 END) as rejeitados,
                COUNT(CASE WHEN status = 'EXPORTADO' THEN 1 END) as exportados
            ")
            ->first();

        $contadores = [
            'pendentes' => $stats->pendentes,
            'conferidos' => $stats->conferidos,
            'rejeitados' => $stats->rejeitados,
            'exportados' => $stats->exportados,
        ];

        // Pendentes por setor e outros dados mantidos...
        $pendentesPorSetor = LancamentoSetorial::where('status', LancamentoStatus::PENDENTE)
            ->selectRaw('setor_origem_id, COUNT(*) as total')
            ->groupBy('setor_origem_id')
            ->with('setorOrigem')
            ->get()
            ->map(fn ($item) => [
                'setor' => $item->setorOrigem->sigla ?? $item->setorOrigem->nome,
                'total' => $item->total,
            ]);

        $lancamentosPorCompetencia = LancamentoSetorial::selectRaw('competencia, status, COUNT(*) as total')
            ->whereNotNull('competencia')
            ->groupBy('competencia', 'status')
            ->orderBy('competencia', 'desc')
            ->limit(30)
            ->get();

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
