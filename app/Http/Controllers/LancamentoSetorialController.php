<?php

namespace App\Http\Controllers;

use App\Enums\LancamentoStatus;
use App\Http\Requests\AprovarSetorialEmLoteRequest;
use App\Http\Requests\SolicitarEstornoRequest;
use App\Http\Requests\StoreLancamentoSetorialRequest;
use App\Http\Requests\UpdateLancamentoSetorialRequest;
use App\Models\LancamentoSetorial;
use App\Models\Servidor;
use App\Services\EstornoLancamentoService;
use App\Services\LancamentoSetorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class LancamentoSetorialController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = LancamentoSetorial::where('setor_origem_id', $user->setor_id)
            ->with(['servidor', 'evento', 'setorOrigem']);

        $filtros = $request->only(['competencia', 'servidor_id', 'evento_id', 'busca']);

        if ($request->filled('status')) {
            $filtros['status'] = $request->status;
        } else {
            $query->whereNotIn('status', [LancamentoStatus::EXPORTADO->value]);
        }

        $query->filtrar($filtros);

        $lancamentos = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $servidores = Servidor::where('setor_id', $user->setor_id)
            ->where('ativo', true)->orderBy('nome')->get();
        $eventos = $user->setor->eventosPermitidos()
            ->where('eventos_folha.ativo', true)->orderBy('eventos_folha.descricao')->get();
        $competencias = LancamentoSetorial::where('setor_origem_id', $user->setor_id)
            ->select('competencia')->distinct()->orderBy('competencia', 'desc')->pluck('competencia');

        return view('lancamentos.index', [
            'lancamentos' => $lancamentos,
            'servidores' => $servidores,
            'eventos' => $eventos,
            'competencias' => $competencias,
            'filtros' => $request->only(['competencia', 'status', 'servidor_id', 'evento_id', 'busca']),
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();
        $setor = $user->setor;

        $servidores = Servidor::where('setor_id', $setor->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        $eventos = $setor->eventosPermitidos()
            ->where('eventos_folha.ativo', true)
            ->orderBy('eventos_folha.descricao')
            ->get();

        $competenciaAtual = now()->format('Y-m');

        return view('lancamentos.create', [
            'servidores' => $servidores,
            'eventos' => $eventos,
            'competenciaAtual' => $competenciaAtual,
        ]);
    }

    public function store(
        StoreLancamentoSetorialRequest $request,
        LancamentoSetorialService $service
    ): RedirectResponse {
        try {
            $service->criar($request->validated(), auth()->user());

            return redirect()
                ->route('lancamentos.index')
                ->with('success', 'Lançamento criado com sucesso!');
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(LancamentoSetorial $lancamento): View
    {
        $this->authorize('view', $lancamento);

        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador', 'conferidoSetorialPor']);

        return view('lancamentos.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function edit(LancamentoSetorial $lancamento): View
    {
        $this->authorize('update', $lancamento);
        $user = auth()->user();

        if ($lancamento->atingiuLimiteRejeicoes()) {
            return redirect()
                ->route('lancamentos.index')
                ->withErrors(['error' => "Este lançamento atingiu o limite de {$lancamento->contarRejeicoes()} rejeições e não pode mais ser re-submetido. Crie um novo lançamento."]);
        }

        $servidores = Servidor::where('setor_id', $user->setor_id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        $setor = $user->setor;
        $eventos = $setor->eventosPermitidos()
            ->where('eventos_folha.ativo', true)
            ->orderBy('eventos_folha.descricao')
            ->get();

        return view('lancamentos.edit', [
            'lancamento' => $lancamento,
            'servidores' => $servidores,
            'eventos' => $eventos,
        ]);
    }

    public function update(
        UpdateLancamentoSetorialRequest $request,
        LancamentoSetorial $lancamento,
        LancamentoSetorialService $service
    ): RedirectResponse {
        try {
            $this->authorize('update', $lancamento);
            $service->atualizar($lancamento, $request->validated(), auth()->user());

            return redirect()
                ->route('lancamentos.index')
                ->with('success', 'Lançamento atualizado com sucesso!');
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(
        LancamentoSetorial $lancamento,
        LancamentoSetorialService $service
    ): RedirectResponse {
        $this->authorize('delete', $lancamento);
        $service->excluir($lancamento);

        return redirect()
            ->route('lancamentos.index')
            ->with('success', 'Lançamento movido para a lixeira!');
    }

    public function lixeira(): View
    {
        $user = auth()->user();

        $lancamentos = LancamentoSetorial::onlyTrashed()
            ->where('setor_origem_id', $user->setor_id)
            ->with(['servidor', 'evento'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        return view('lancamentos.lixeira', [
            'lancamentos' => $lancamentos,
        ]);
    }

    public function restaurar(
        int $id,
        LancamentoSetorialService $service
    ): RedirectResponse {
        try {
            $service->restaurar($id, auth()->user());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('lancamentos.lixeira')
                ->withErrors(['error' => 'Não foi possível restaurar: '.$e->getMessage()]);
        }

        return redirect()
            ->route('lancamentos.lixeira')
            ->with('success', 'Lançamento restaurado com sucesso!');
    }

    public function aprovarSetorial(
        LancamentoSetorial $lancamento,
        LancamentoSetorialService $service
    ): RedirectResponse {
        $this->authorize('aprovarSetorial', $lancamento);

        try {
            $service->aprovarSetorial($lancamento, auth()->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', 'Lançamento conferido com sucesso! Aguarda aprovação da Central.');
    }

    public function aprovarSetorialEmLote(
        AprovarSetorialEmLoteRequest $request,
        LancamentoSetorialService $service
    ): RedirectResponse {
        $resultado = $service->aprovarSetorialEmLote(
            $request->validated('lancamento_ids'),
            auth()->user()
        );

        $mensagem = "{$resultado['aprovados']} lançamento(s) conferido(s) pelo setor!";
        if ($resultado['ignoradosSegregacao'] > 0) {
            $mensagem .= " ({$resultado['ignoradosSegregacao']} ignorado(s) por segregação de função — você é o criador)";
        }

        return redirect()->back()->with('success', $mensagem);
    }

    public function cancelar(
        LancamentoSetorial $lancamento,
        LancamentoSetorialService $service
    ): RedirectResponse {
        $this->authorize('cancelar', $lancamento);
        $service->cancelar($lancamento);

        return redirect()
            ->back()
            ->with('success', 'Lançamento cancelado de forma definitiva!');
    }

    public function solicitarEstorno(
        SolicitarEstornoRequest $request,
        LancamentoSetorial $lancamento,
        EstornoLancamentoService $service
    ): RedirectResponse {
        $this->authorize('solicitarEstorno', $lancamento);
        $service->solicitar($lancamento, $request->validated('motivo_estorno'));

        return redirect()
            ->back()
            ->with('success', 'Solicitação de estorno enviada para a Central!');
    }
}
