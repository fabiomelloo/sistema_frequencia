<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\EventoFolha;
use App\Models\Competencia;
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
                COUNT(CASE WHEN status = 'ESTORNADO' THEN 1 END) as ESTORNADO
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
        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador', 'conferidoSetorialPor']);

        return view('painel.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function aprovar(LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível aprovar.']);
        }

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
        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível rejeitar.']);
        }

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

        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível estornar.']);
        }

        $dadosAntes = $lancamento->toArray();

        // Volta para PENDENTE para re-entrar no workflow de conferência
        $lancamento->status = LancamentoStatus::PENDENTE;
        $lancamento->exportado_em = null;
        $lancamento->id_validador = null;
        $lancamento->validated_at = null;
        $lancamento->conferido_setorial_por = null;
        $lancamento->conferido_setorial_em = null;
        $lancamento->save();

        AuditService::registrar('ESTORNOU', 'LancamentoSetorial', $lancamento->id,
            "Lançamento estornado — retorna ao status PENDENTE para re-conferência",
            $dadosAntes, $lancamento->fresh()->toArray()
        );

        return redirect()
            ->back()
            ->with('success', 'Lançamento estornado! Retornou ao status PENDENTE para nova conferência.');
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
                ->withErrors(['error' => 'Ocorreu um erro ao exportar os lançamentos. Tente novamente ou contacte o administrador.']);
        }
    }
}
