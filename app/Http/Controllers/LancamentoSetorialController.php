<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Http\Requests\StoreLancamentoSetorialRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LancamentoSetorialController extends Controller
{
    public function create(): View
    {
        $user = auth()->user();
        $setor = $user->setor;

        // Servidores do setor do usuário
        $servidores = Servidor::where('setor_id', $setor->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        // Eventos permitidos para o setor
        $eventos = EventoFolha::whereHas('setoresComDireito', function ($q) use ($setor) {
            $q->where('setor_id', $setor->id);
        })
            ->where('ativo', true)
            ->orderBy('descricao')
            ->get();

        return view('lancamentos.create', [
            'servidores' => $servidores,
            'eventos' => $eventos,
        ]);
    }

    public function store(StoreLancamentoSetorialRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validated();

        // Criar lançamento com status PENDENTE
        // Validação de evento já está no Request
        LancamentoSetorial::create([
            'servidor_id' => $validated['servidor_id'],
            'evento_id' => $validated['evento_id'],
            'setor_origem_id' => $user->setor_id,
            'dias_lancados' => $validated['dias_lancados'] ?? null,
            'valor' => $validated['valor'] ?? null,
            'porcentagem_insalubridade' => $validated['porcentagem_insalubridade'] ?? null,
            'observacao' => $validated['observacao'] ?? null,
            'status' => 'PENDENTE',
        ]);

        return redirect()
            ->route('lancamentos.index')
            ->with('success', 'Lançamento criado com sucesso!');
    }

    public function index(): View
    {
        $user = auth()->user();
        $lancamentos = LancamentoSetorial::where('setor_origem_id', $user->setor_id)
            ->where('status', '!=', 'EXPORTADO')
            ->with(['servidor', 'evento', 'setorOrigem'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('lancamentos.index', [
            'lancamentos' => $lancamentos,
        ]);
    }

    public function show(LancamentoSetorial $lancamento): View
    {
        $user = auth()->user();

        // Validar propriedade
        if ($lancamento->setor_origem_id !== $user->setor_id) {
            abort(403, 'Não autorizado.');
        }

        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador']);

        return view('lancamentos.show', [
            'lancamento' => $lancamento,
        ]);
    }

    public function edit(LancamentoSetorial $lancamento): View
    {
        $user = auth()->user();

        // Validar propriedade e status
        if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
            abort(403, 'Não autorizado.');
        }

        $servidores = Servidor::where('setor_id', $user->setor_id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        $eventos = EventoFolha::whereHas('setoresComDireito', function ($q) use ($user) {
            $q->where('setor_id', $user->setor_id);
        })
            ->where('ativo', true)
            ->orderBy('descricao')
            ->get();

        return view('lancamentos.edit', [
            'lancamento' => $lancamento,
            'servidores' => $servidores,
            'eventos' => $eventos,
        ]);
    }

    public function update(StoreLancamentoSetorialRequest $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        $user = auth()->user();

        if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
            abort(403, 'Não autorizado.');
        }

        $validated = $request->validated();

        // Validação de evento já está no Request
        $lancamento->update([
            'servidor_id' => $validated['servidor_id'],
            'evento_id' => $validated['evento_id'],
            'dias_lancados' => $validated['dias_lancados'] ?? null,
            'valor' => $validated['valor'] ?? null,
            'porcentagem_insalubridade' => $validated['porcentagem_insalubridade'] ?? null,
            'observacao' => $validated['observacao'] ?? null,
        ]);

        return redirect()
            ->route('lancamentos.index')
            ->with('success', 'Lançamento atualizado com sucesso!');
    }

    public function destroy(LancamentoSetorial $lancamento): RedirectResponse
    {
        $user = auth()->user();

        if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
            abort(403, 'Não autorizado.');
        }

        $lancamento->delete();

        return redirect()
            ->route('lancamentos.index')
            ->with('success', 'Lançamento deletado com sucesso!');
    }
}
