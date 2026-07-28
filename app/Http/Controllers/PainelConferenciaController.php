<?php

namespace App\Http\Controllers;

use App\Enums\LancamentoStatus;
use App\Http\Requests\AprovarEmLoteRequest;
use App\Http\Requests\EstornarLancamentoRequest;
use App\Http\Requests\ExportarLancamentosRequest;
use App\Http\Requests\RecusarEstornoRequest;
use App\Http\Requests\RejeitarLancamentoRequest;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Services\ConferenciaLancamentoService;
use App\Services\EstornoLancamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PainelConferenciaController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', LancamentoStatus::PENDENTE->value);

        if (! in_array($status, LancamentoStatus::valores())) {
            $status = LancamentoStatus::PENDENTE->value;
        }

        $query = LancamentoSetorial::where('status', $status)
            ->with(['servidor', 'evento', 'setorOrigem', 'validador']);

        $filtros = array_filter($request->only(['competencia', 'evento_id', 'busca']));
        if ($request->filled('setor_id')) {
            $filtros['setor_id'] = $request->setor_id;
        }

        $query->filtrar($filtros);

        $lancamentos = $query->orderBy('created_at', 'asc')->paginate(15)->withQueryString();

        $statsRow = LancamentoSetorial::selectRaw("
                COUNT(CASE WHEN status = 'PENDENTE' THEN 1 END) as PENDENTE,
                COUNT(CASE WHEN status = 'CONFERIDO_SETORIAL' THEN 1 END) as CONFERIDO_SETORIAL,
                COUNT(CASE WHEN status = 'CONFERIDO' THEN 1 END) as CONFERIDO,
                COUNT(CASE WHEN status = 'REJEITADO' THEN 1 END) as REJEITADO,
                COUNT(CASE WHEN status = 'EXPORTADO' THEN 1 END) as EXPORTADO,
                COUNT(CASE WHEN status = 'ESTORNADO' THEN 1 END) as ESTORNADO,
                COUNT(CASE WHEN status = 'CANCELADO' THEN 1 END) as CANCELADO,
                COUNT(CASE WHEN status = 'ESTORNO_SOLICITADO' THEN 1 END) as ESTORNO_SOLICITADO
            ")->first();

        $contadores = [];
        foreach (LancamentoStatus::cases() as $s) {
            $contadores[$s->value] = $statsRow->{$s->value} ?? 0;
        }

        $setores = Setor::where('ativo', true)->orderBy('nome')->get();
        $eventos = EventoFolha::where('ativo', true)->orderBy('descricao')->get();
        $competencias = LancamentoSetorial::select('competencia')
            ->distinct()->orderBy('competencia', 'desc')->pluck('competencia');

        return view('painel.index', [
            'lancamentos' => $lancamentos,
            'statusAtual' => $status,
            'contadores' => $contadores,
            'setores' => $setores,
            'eventos' => $eventos,
            'competencias' => $competencias,
            'filtros' => $request->only(['competencia', 'setor_id', 'evento_id', 'busca']),
        ]);
    }

    public function show(LancamentoSetorial $lancamento): View
    {
        // Lançamentos cancelados não devem ser visíveis no painel de conferência
        if ($lancamento->isCancelado()) {
            abort(404);
        }

        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador', 'conferidoSetorialPor']);

        return view('painel.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function aprovar(
        LancamentoSetorial $lancamento,
        ConferenciaLancamentoService $service
    ): RedirectResponse {
        try {
            $service->aprovar($lancamento, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Lançamento aprovado com sucesso!');
    }

    public function rejeitar(
        RejeitarLancamentoRequest $request,
        LancamentoSetorial $lancamento,
        ConferenciaLancamentoService $service
    ): RedirectResponse {
        try {
            $service->rejeitar(
                $lancamento,
                $request->validated('motivo_rejeicao'),
                auth()->user()
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Lançamento rejeitado com sucesso!');
    }

    public function aprovarEmLote(
        AprovarEmLoteRequest $request,
        ConferenciaLancamentoService $service
    ): RedirectResponse {
        try {
            $resultado = $service->aprovarEmLote(
                $request->validated('lancamento_ids'),
                auth()->user()
            );
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['error' => 'Erro ao processar aprovação em lote: '.$e->getMessage()]);
        }

        $mensagem = "Processamento concluído: {$resultado['aprovados']} aprovados.";
        if ($resultado['ignorados'] > 0) {
            $mensagem .= " ({$resultado['ignorados']} ignorados por status incorreto)";
        }
        if ($resultado['competenciaFechada'] > 0) {
            $mensagem .= " ({$resultado['competenciaFechada']} ignorados por competência fechada)";
        }

        return redirect()->route('painel.index')->with('success', $mensagem);
    }

    public function estornar(
        EstornarLancamentoRequest $request,
        LancamentoSetorial $lancamento,
        EstornoLancamentoService $service
    ): RedirectResponse {
        try {
            $service->aprovar($lancamento, $request->validated('motivo_estorno'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Lançamento estornado! Setor notificado.');
    }

    public function recusarEstorno(
        RecusarEstornoRequest $request,
        LancamentoSetorial $lancamento,
        EstornoLancamentoService $service
    ): RedirectResponse {
        try {
            $service->recusar($lancamento, $request->validated('motivo_recusa'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Solicitação de estorno recusada.');
    }

    public function exportar(
        ExportarLancamentosRequest $request,
        ConferenciaLancamentoService $service
    ): StreamedResponse|RedirectResponse {
        try {
            $resultado = $service->exportar($request->validated('competencia'));

            return Storage::disk('local')->download(
                $resultado['caminhoArquivo'],
                $resultado['nomeArquivo'],
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        } catch (\Throwable $e) {
            \Log::error('Erro ao exportar lançamentos', [
                'erro' => $e->getMessage(),
                'usuario_id' => auth()->id(),
            ]);

            return redirect()->route('painel.index')->withErrors(['error' => $e->getMessage()]);
        }
    }
}
