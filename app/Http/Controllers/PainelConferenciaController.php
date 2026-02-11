<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Services\GeradorTxtFolhaService;
use App\Services\AuditService;
use App\Services\NotificacaoService;
use App\Http\Requests\RejeitarLancamentoRequest;
use App\Enums\LancamentoStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PainelConferenciaController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', LancamentoStatus::PENDENTE->value);

        if (!in_array($status, LancamentoStatus::valores())) {
            $status = LancamentoStatus::PENDENTE->value;
        }

        $query = LancamentoSetorial::where('status', $status)
            ->with(['servidor', 'evento', 'setorOrigem', 'validador']);

        // Filtros
        if ($request->filled('competencia')) {
            $query->where('competencia', $request->competencia);
        }
        if ($request->filled('setor_id')) {
            $query->where('setor_origem_id', $request->setor_id);
        }
        if ($request->filled('evento_id')) {
            $query->where('evento_id', $request->evento_id);
        }
        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->whereHas('servidor', function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('matricula', 'like', "%{$busca}%");
            });
        }

        $lancamentos = $query->orderBy('created_at', 'asc')->paginate(15)->withQueryString();

        $contadores = [
            'PENDENTE' => LancamentoSetorial::where('status', LancamentoStatus::PENDENTE)->count(),
            'CONFERIDO' => LancamentoSetorial::where('status', LancamentoStatus::CONFERIDO)->count(),
            'REJEITADO' => LancamentoSetorial::where('status', LancamentoStatus::REJEITADO)->count(),
            'EXPORTADO' => LancamentoSetorial::where('status', LancamentoStatus::EXPORTADO)->count(),
        ];

        // Dados para filtros
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
        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador']);

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

        $lancamento->status = LancamentoStatus::CONFERIDO;
        $lancamento->id_validador = auth()->id();
        $lancamento->validated_at = now();
        $lancamento->save();

        $lancamento->load(['servidor', 'evento']);

        AuditService::aprovou('LancamentoSetorial', $lancamento->id,
            "Lançamento aprovado: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
        );

        NotificacaoService::lancamentoAprovado($lancamento);

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

        $lancamento->status = LancamentoStatus::REJEITADO;
        $lancamento->motivo_rejeicao = $validated['motivo_rejeicao'];
        $lancamento->id_validador = auth()->id();
        $lancamento->validated_at = now();
        $lancamento->save();

        $lancamento->load(['servidor', 'evento']);

        AuditService::rejeitou('LancamentoSetorial', $lancamento->id,
            "Lançamento rejeitado: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}. Motivo: {$validated['motivo_rejeicao']}"
        );

        NotificacaoService::lancamentoRejeitado($lancamento);

        return redirect()
            ->back()
            ->with('success', 'Lançamento rejeitado com sucesso!');
    }

    /**
     * Aprovação em lote
     */
    public function aprovarEmLote(Request $request): RedirectResponse
    {
        $request->validate([
            'lancamento_ids' => ['required', 'array', 'min:1'],
            'lancamento_ids.*' => ['integer', 'exists:lancamentos_setoriais,id'],
        ]);

        $ids = $request->lancamento_ids;
        $aprovados = 0;

        foreach ($ids as $id) {
            $lancamento = LancamentoSetorial::find($id);
            if ($lancamento && $lancamento->isPendente()) {
                $lancamento->status = LancamentoStatus::CONFERIDO;
                $lancamento->id_validador = auth()->id();
                $lancamento->validated_at = now();
                $lancamento->save();

                $lancamento->load(['servidor', 'evento']);

                AuditService::aprovou('LancamentoSetorial', $lancamento->id,
                    "Lançamento aprovado em lote: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
                );

                NotificacaoService::lancamentoAprovado($lancamento);
                $aprovados++;
            }
        }

        return redirect()
            ->back()
            ->with('success', "{$aprovados} lançamento(s) aprovado(s) com sucesso!");
    }

    public function exportar(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $competencia = $request->get('competencia');

            $servico = app(GeradorTxtFolhaService::class);
            $resultado = $servico->gerar($competencia);

            $idsExportados = $resultado['idsExportados']->toArray();

            LancamentoSetorial::whereIn('id', $idsExportados)
                ->each(function ($lancamento) {
                    $lancamento->status = LancamentoStatus::EXPORTADO;
                    $lancamento->exportado_em = now();
                    $lancamento->save();
                });

            AuditService::exportou('LancamentoSetorial', null,
                "Exportados {$resultado['quantidade']} lançamentos. Arquivo: {$resultado['nomeArquivo']}"
            );

            NotificacaoService::lancamentosExportados($idsExportados);

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
                ->withErrors(['error' => 'Erro ao exportar: ' . $e->getMessage()]);
        }
    }
}
