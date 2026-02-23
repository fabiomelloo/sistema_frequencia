<?php

namespace App\Http\Controllers;

use App\Services\RelatorioService;
use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Models\Servidor;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public function resumo(Request $request, RelatorioService $service): View
    {
        $competencia = $request->get('competencia', now()->format('Y-m'));
        $dados = $service->resumoCompetencia($competencia);
        
        $competencias = LancamentoSetorial::select('competencia')
            ->distinct()->orderBy('competencia', 'desc')->pluck('competencia');

        return view('admin.relatorios.resumo', [
            'estatisticas' => [
                'total_lancamentos' => $dados['total_lancamentos'],
                'total_valor' => $dados['valor_geral'],
                'total_pendentes' => $dados['por_status'][\App\Enums\LancamentoStatus::PENDENTE->value] ?? 0,
                'total_setores' => count($dados['por_setor']),
            ],
            'porSetor' => $dados['por_setor'],
            'porEvento' => $dados['por_evento'],
            'competencias' => $competencias,
            'competenciaSelecionada' => $competencia,
        ]);
    }

    public function comparativo(Request $request, RelatorioService $service): View
    {
        $compA = $request->get('competencia_a', now()->subMonth()->format('Y-m'));
        $compB = $request->get('competencia_b', now()->format('Y-m'));
        
        $dados = $service->comparativo($compA, $compB);
        
        $competencias = LancamentoSetorial::select('competencia')
            ->distinct()->orderBy('competencia', 'desc')->pluck('competencia');

        return view('admin.relatorios.comparativo', [
            'dados' => $dados,
            'competencias' => $competencias,
            'compA' => $compA,
            'compB' => $compB,
        ]);
    }

    public function folhaEspelho(Request $request, RelatorioService $service): View
    {
        $servidorId = $request->get('servidor_id');
        $competencia = $request->get('competencia', now()->format('Y-m'));

        $dados = null;
        if ($servidorId) {
            $dados = $service->folhaEspelho((int) $servidorId, $competencia);
            $servidorObj = Servidor::find($servidorId);
            if ($servidorObj) {
                AuditService::leu('Servidor', $servidorId, "Gerou relatório Espelho da Folha do servidor {$servidorObj->nome} para a competência {$competencia}");
            }
        }

        $servidores = Servidor::where('ativo', true)->orderBy('nome')->get();
        $competencias = LancamentoSetorial::select('competencia')
            ->distinct()->orderBy('competencia', 'desc')->pluck('competencia');

        return view('admin.relatorios.folha-espelho', [
            'dados' => $dados,
            'servidores' => $servidores,
            'competencias' => $competencias,
            'servidorId' => $servidorId,
            'competenciaSelecionada' => $competencia,
        ]);
    }

    public function exportarCsv(Request $request, RelatorioService $service): StreamedResponse
    {
        $competencia = $request->get('competencia', now()->format('Y-m'));
        $csv = $service->gerarCsv($competencia);

        AuditService::exportou('LancamentoSetorial', null, "Exportou relatório geral em CSV da competência {$competencia}");

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF"; // BOM UTF-8
            echo $csv;
        }, "relatorio_{$competencia}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
