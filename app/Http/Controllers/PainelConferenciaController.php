<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
use App\Http\Requests\EstornarLancamentoRequest;
use App\Enums\LancamentoStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
     * Aprovação em lote (requer CONFERIDO_SETORIAL â€” respeita workflow de 2 etapas)
     * Regra: cada lançamento deve ter competência aberta
     */
    public function aprovarEmLote(AprovarEmLoteRequest $request): RedirectResponse
    {
        $ids = $request->validated()['lancamento_ids'];
        

        $lancamentos = LancamentoSetorial::whereIn('id', $ids)
            ->with(['servidor', 'evento'])
            ->get();
        

        $porCompetencia = $lancamentos->groupBy('competencia');

        $aprovados = 0;
        $ignorados = 0;
        $competenciaFechada = 0;

        DB::beginTransaction();
        try {
            foreach ($porCompetencia as $competencia => $grupo) {

                if (!Competencia::referenciaAberta($competencia)) {
                    $competenciaFechada += $grupo->count();
                    continue;
                }

                foreach ($grupo as $lancamento) {
                    if (!$lancamento->isConferidoSetorial()) {
                        $ignorados++;
                        continue;
                    }

                    $dadosAntes = $lancamento->toArray();

                    $lancamento->status = LancamentoStatus::CONFERIDO;
                    $lancamento->id_validador = auth()->id();
                    $lancamento->validated_at = now();
                    $lancamento->save();

                    AuditService::aprovou('LancamentoSetorial', $lancamento->id,
                        "Lançamento Aprovado em Lote",
                        $dadosAntes, 
                        $lancamento->toArray()
                    );

                    NotificacaoService::lancamentoAprovado($lancamento);

                    $aprovados++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors(['error' => 'Erro ao processar aprovação em lote: ' . $e->getMessage()]);
        }

        $mensagem = "Processamento concluído: {$aprovados} aprovados.";
        if ($ignorados > 0) {
            $mensagem .= " ({$ignorados} ignorados por status incorreto)";
        }
        if ($competenciaFechada > 0) {
            $mensagem .= " ({$competenciaFechada} ignorados por competência fechada)";
        }

        return redirect()
            ->route('painel.index')
            ->with('success', $mensagem);
    }

    /**
     * Estorno: EXPORTADO ou ESTORNO_SOLICITADO â†’ ESTORNADO (reverter exportação)
     * Regra #11: exige motivo obrigatório se feito direto. Se for aprovação de solicitacao, usa o motivo enviado.
     * Regra #12: notifica o setor
     */
    public function estornar(EstornarLancamentoRequest $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        if (!$lancamento->isExportado() && !$lancamento->isEstornoSolicitado()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos EXPORTADOS ou com ESTORNO SOLICITADO podem ser estornados.']);
        }

        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível estornar.']);
        }

        $dadosAntes = $lancamento->toArray();


        $motivo = $request->input('motivo_estorno')
            ?? $lancamento->motivo_estorno
            ?? $lancamento->motivo_rejeicao
            ?? '';


        $lancamento->status = LancamentoStatus::ESTORNADO;
        $lancamento->motivo_estorno = $motivo;
        $lancamento->exportado_em = null;
        $lancamento->id_validador = null;
        $lancamento->validated_at = null;
        $lancamento->conferido_setorial_por = null;
        $lancamento->conferido_setorial_em = null;
        $lancamento->save();

        $lancamento->load(['servidor', 'evento']);

        AuditService::registrar('ESTORNOU', 'LancamentoSetorial', $lancamento->id,
            "Lançamento estornado â€” Motivo: {$motivo}",
            $dadosAntes, $lancamento->fresh()->toArray()
        );

        NotificacaoService::lancamentoEstornado($lancamento, $motivo);

        return redirect()
            ->back()
            ->with('success', 'Lançamento estornado! Setor notificado.');
    }

    public function exportar(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $competencia = $request->get('competencia');

            if (!$competencia) {
                return redirect()
                    ->route('painel.index')
                    ->withErrors(['error' => 'Selecione uma competência para exportar.']);
            }

            // validar que a competência está aberta antes de exportar
            if (!Competencia::referenciaAberta($competencia)) {
                return redirect()
                    ->route('painel.index')
                    ->withErrors(['error' => "A competência {$competencia} está fechada. Não é possível exportar."]);
            }

            // Verificar se há servidores inativos nos lançamentos a exportar
            $lancamentosInvalidos = LancamentoSetorial::where('competencia', $competencia)
                ->where('status', LancamentoStatus::CONFERIDO->value)
                ->whereHas('servidor', function ($q) {
                    $q->where('ativo', false);
                })
                ->with('servidor')
                ->get();

            if ($lancamentosInvalidos->isNotEmpty()) {
                $nomes = $lancamentosInvalidos->pluck('servidor.nome')->implode(', ');
                return redirect()
                    ->route('painel.index')
                    ->withErrors(['error' => "Existem lançamentos com servidores inativos: {$nomes}. Rejeite-os antes de exportar."]);
            }

            $servico = app(GeradorTxtFolhaService::class);

            $nomeArquivo = DB::transaction(function () use ($servico, $competencia) {
                $resultado = $servico->gerar($competencia);
                $idsExportados = $resultado['idsExportados']->toArray();


                LancamentoSetorial::whereIn('id', $idsExportados)
                    ->update([
                        'status' => LancamentoStatus::EXPORTADO->value,
                        'exportado_em' => now(),
                    ]);

                AuditService::exportou('LancamentoSetorial', null,
                    "Exportados {$resultado['quantidade']} lançamentos. Arquivo: {$resultado['nomeArquivo']}"
                );

                NotificacaoService::lancamentosExportados($idsExportados);

                return $resultado['nomeArquivo'];
            });

            return response()
                ->download(storage_path("app/{$nomeArquivo}"))
                ->deleteFileAfterSend(false);
                
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

