<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Services\GeradorTxtFolhaService;
use App\Http\Requests\RejeitarLancamentoRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

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

        // Contadores para evitar queries nas views
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
        // Validar que só pode aprovar se status é PENDENTE
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
        // Validar que só pode rejeitar se status é PENDENTE
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

    public function exportar(): Response
    {
        try {
            $servico = new GeradorTxtFolhaService();
            $resultado = $servico->gerar();

            // Marcar lançamentos como exportados (responsabilidade do Controller)
            LancamentoSetorial::whereIn('id', $resultado['idsExportados'])
                ->update([
                    'status' => 'EXPORTADO',
                    'exportado_em' => now(),
                ]);

            return response()
                ->download(storage_path("app/{$resultado['nomeArquivo']}"))
                ->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['export' => $e->getMessage()]);
        }
    }
}
