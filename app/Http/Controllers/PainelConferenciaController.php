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
use App\Http\Requests\AprovarEmLoteRequest;
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
            $busca = addcslashes($request->busca, '%_');
            $query->whereHas('servidor', function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('matricula', 'like', "%{$busca}%");
            });
        }

        $lancamentos = $query->orderBy('created_at', 'asc')->paginate(15)->withQueryString();

        $contadores = [];
        foreach (LancamentoStatus::cases() as $s) {
            $contadores[$s->value] = LancamentoSetorial::where('status', $s)->count();
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
        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador', 'conferidoSetorialPor']);

        return view('painel.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function aprovar(LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isConferidoSetorial()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos com status CONFERIDO SETORIAL podem ser aprovados pela Central.']);
        }

        $lancamento->status = LancamentoStatus::CONFERIDO;
        $lancamento->id_validador = auth()->id();
        $lancamento->validated_at = now();
        $lancamento->save();

        $lancamento->load(['servidor', 'evento']);

        AuditService::aprovou('LancamentoSetorial', $lancamento->id,
            "Lançamento aprovado (Central): {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
        );

        NotificacaoService::lancamentoAprovado($lancamento);

        return redirect()
            ->back()
            ->with('success', 'Lançamento aprovado com sucesso!');
    }

    public function rejeitar(RejeitarLancamentoRequest $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isPendente() && !$lancamento->isConferidoSetorial()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos PENDENTES ou CONFERIDOS SETORIAL podem ser rejeitados.']);
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
     * Aprovação em lote (requer CONFERIDO_SETORIAL — respeita workflow de 2 etapas)
     */
    public function aprovarEmLote(AprovarEmLoteRequest $request): RedirectResponse
    {
        $ids = $request->validated()['lancamento_ids'];
        $aprovados = 0;
        $ignorados = 0;

        foreach ($ids as $id) {
            $lancamento = LancamentoSetorial::find($id);
            if ($lancamento && $lancamento->isConferidoSetorial()) {
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
            } else {
                $ignorados++;
            }
        }

        $mensagem = "{$aprovados} lançamento(s) aprovado(s) com sucesso!";
        if ($ignorados > 0) {
            $mensagem .= " ({$ignorados} ignorado(s) por não estarem conferidos pelo setor.)";
        }

        return redirect()
            ->back()
            ->with('success', $mensagem);
    }

    /**
     * Estorno: EXPORTADO → CONFERIDO (reverter exportação)
     */
    public function estornar(LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isExportado()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos EXPORTADOS podem ser estornados.']);
        }

        $dadosAntes = $lancamento->toArray();

        $lancamento->status = LancamentoStatus::ESTORNADO;
        $lancamento->exportado_em = null;
        $lancamento->save();

        AuditService::registrar('ESTORNOU', 'LancamentoSetorial', $lancamento->id,
            "Lançamento estornado (exportação revertida)",
            $dadosAntes, $lancamento->fresh()->toArray()
        );

        return redirect()
            ->back()
            ->with('success', 'Lançamento estornado com sucesso! Retornará para conferência.');
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
