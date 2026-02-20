<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use App\Models\Setor;
use App\Services\ServidorCicloVidaService;
use App\Services\AuditService;
use App\Http\Requests\TransferirServidorRequest;
use App\Http\Requests\DesligarServidorRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class ServidorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
    }

    public function index(): View
    {
        $servidores = Servidor::with('setor')
            ->orderBy('nome')
            ->paginate(20);

        return view('admin.servidores.index', [
            'servidores' => $servidores,
        ]);
    }

    public function create(): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.servidores.create', [
            'setores' => $setores,
        ]);
    }

    public function store(\App\Http\Requests\StoreServidorRequest $request): RedirectResponse
    {
        Servidor::create($request->validated());

        return redirect()
            ->route('admin.servidores.index')
            ->with('success', 'Servidor criado com sucesso!');
    }

    public function show(Servidor $servidor): View
    {
        $servidor->load(['setor', 'lancamentos.evento', 'lancamentos.setorOrigem']);

        return view('admin.servidores.show', [
            'servidor' => $servidor,
        ]);
    }

    public function edit(Servidor $servidor): View
    {
        $setores = Setor::where('ativo', true)->orderBy('nome')->get();

        return view('admin.servidores.edit', [
            'servidor' => $servidor,
            'setores' => $setores,
        ]);
    }

    public function update(\App\Http\Requests\UpdateServidorRequest $request, Servidor $servidor): RedirectResponse
    {
        $servidor->update($request->validated());

        return redirect()
            ->route('admin.servidores.index')
            ->with('success', 'Servidor atualizado com sucesso!');
    }

    public function destroy(Servidor $servidor): RedirectResponse
    {
        if ($servidor->lancamentos()->count() > 0) {
            return redirect()
                ->route('admin.servidores.index')
                ->with('error', 'Não é possível deletar um servidor que possui lançamentos vinculados.');
        }

        $servidor->delete();

        AuditService::excluiu('Servidor', $servidor->id, "Servidor excluído: {$servidor->nome}");

        return redirect()
            ->route('admin.servidores.index')
            ->with('success', 'Servidor deletado com sucesso!');
    }

    /**
     * Ativa um servidor previamente desativado.
     */
    public function ativar(Servidor $servidor): RedirectResponse
    {
        $servidor->ativo = true;
        $servidor->data_desligamento = null;
        $servidor->save();

        AuditService::registrar(
            'ATIVOU_SERVIDOR',
            'Servidor',
            $servidor->id,
            "Servidor reativado: {$servidor->nome}"
        );

        return redirect()
            ->route('admin.servidores.show', $servidor)
            ->with('success', 'Servidor reativado com sucesso!');
    }

    /**
     * Exibe formulário de transferência de servidor.
     */
    public function formTransferir(Servidor $servidor): View
    {
        $setores = Setor::where('ativo', true)
            ->where('id', '!=', $servidor->setor_id)
            ->orderBy('nome')
            ->get();

        return view('admin.servidores.transferir', [
            'servidor' => $servidor,
            'setores' => $setores,
        ]);
    }

    /**
     * Processa transferência de servidor entre setores.
     */
    public function transferir(
        TransferirServidorRequest $request,
        Servidor $servidor,
        ServidorCicloVidaService $service
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            
            $resultado = $service->transferirServidor(
                $servidor,
                $validated['novo_setor_id'],
                Carbon::parse($validated['data_transferencia']),
                $validated['motivo'] ?? null
            );

            $mensagem = "Servidor transferido com sucesso! ";
            $mensagem .= "{$resultado['lancamentos_afetados']} lançamento(s) pendente(s) ";
            $mensagem .= $resultado['acao'] === 'transferidos' 
                ? "foram transferidos para o novo setor."
                : "permanecem no setor de origem.";

            return redirect()
                ->route('admin.servidores.show', $servidor)
                ->with('success', $mensagem);

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Exibe formulário de desligamento de servidor.
     */
    public function formDesligar(Servidor $servidor): View
    {
        // Buscar lançamentos que serão afetados
        $competenciaAtual = now()->format('Y-m');
        $lancamentosAfetados = \App\Models\LancamentoSetorial::where('servidor_id', $servidor->id)
            ->whereIn('status', [
                \App\Enums\LancamentoStatus::PENDENTE,
                \App\Enums\LancamentoStatus::CONFERIDO_SETORIAL,
                \App\Enums\LancamentoStatus::REJEITADO,
            ])
            ->where('competencia', '>=', $competenciaAtual)
            ->count();

        return view('admin.servidores.desligar', [
            'servidor' => $servidor,
            'lancamentos_afetados' => $lancamentosAfetados,
        ]);
    }

    /**
     * Processa desligamento de servidor.
     */
    public function desligar(
        DesligarServidorRequest $request,
        Servidor $servidor,
        ServidorCicloVidaService $service
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            
            $resultado = $service->desligarServidor(
                $servidor,
                Carbon::parse($validated['data_desligamento']),
                $request->getMotivoFinal()
            );

            $mensagem = "Servidor desligado com sucesso! ";
            $mensagem .= "{$resultado['lancamentos_cancelados']} lançamento(s) cancelado(s). ";
            $mensagem .= "{$resultado['setores_notificados']} setor(es) notificado(s).";

            return redirect()
                ->route('admin.servidores.show', $servidor)
                ->with('success', $mensagem);

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
