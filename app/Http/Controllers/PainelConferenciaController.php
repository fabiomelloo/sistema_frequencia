<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Services\GeradorTxtFolhaService;
use App\Http\Requests\RejeitarLancamentoRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PainelConferenciaController extends Controller
{
    public function index(): View
    {
        $status = request('status', 'PENDENTE');

        if (!in_array($status, ['PENDENTE', 'CONFERIDO', 'REJEITADO', 'EXPORTADO'])) {
            $status = 'PENDENTE';
        }

        $lancamentos = LancamentoSetorial::where('status', $status)
            ->with(['servidor', 'evento', 'setorOrigem', 'validador'])
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        $contadores = [
            'PENDENTE' => LancamentoSetorial::where('status', 'PENDENTE')->count(),
            'CONFERIDO' => LancamentoSetorial::where('status', 'CONFERIDO')->count(),
            'REJEITADO' => LancamentoSetorial::where('status', 'REJEITADO')->count(),
            'EXPORTADO' => LancamentoSetorial::where('status', 'EXPORTADO')->count(),
        ];

        return view('painel.index', [
            'lancamentos' => $lancamentos,
            'statusAtual' => $status,
            'contadores' => $contadores,
        ]);
    }

    public function show(LancamentoSetorial $lancamento): View
    {
        return view('painel.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function aprovar(LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isPendente()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos com status PENDENTE podem ser aprovados.']);
        }

        $lancamento->update([
            'status' => 'CONFERIDO',
            'id_validador' => auth()->id(),
            'validated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Lançamento aprovado com sucesso!');
    }

    public function rejeitar(RejeitarLancamentoRequest $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isPendente()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos com status PENDENTE podem ser rejeitados.']);
        }

        $validated = $request->validated();

        $lancamento->update([
            'status' => 'REJEITADO',
            'motivo_rejeicao' => $validated['motivo_rejeicao'],
            'id_validador' => auth()->id(),
            'validated_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Lançamento rejeitado com sucesso!');
    }

    public function exportar(): BinaryFileResponse|RedirectResponse
    {
        try {
            $servico = app(GeradorTxtFolhaService::class);
            $resultado = $servico->gerar();

            LancamentoSetorial::whereIn('id', $resultado['idsExportados'])
                ->update([
                    'status' => 'EXPORTADO',
                    'exportado_em' => now(),
                ]);

            return response()
                ->download(storage_path("app/{$resultado['nomeArquivo']}"))
                ->deleteFileAfterSend(true);
                
        } catch (\Exception $e) {
            \Log::error('Erro ao exportar lançamentos', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'usuario_id' => auth()->id(),
            ]);

            return redirect()
                ->route('painel.index')
                ->withErrors(['error' => 'Erro ao exportar lançamentos: ' . $e->getMessage()]);
        }
    }
}
