<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Http\Requests\StoreLancamentoSetorialRequest;
use App\Services\RegrasLancamentoService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class LancamentoSetorialController extends Controller
{
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

        return view('lancamentos.create', [
            'servidores' => $servidores,
            'eventos' => $eventos,
        ]);
    }

    public function store(
        StoreLancamentoSetorialRequest $request,
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            $servidor = Servidor::findOrFail($validated['servidor_id']);
            $evento = EventoFolha::findOrFail($validated['evento_id']);

            $regrasService->validar($servidor, $evento, $validated);

            LancamentoSetorial::create([
                'servidor_id' => $validated['servidor_id'],
                'evento_id' => $validated['evento_id'],
                'setor_origem_id' => $user->setor_id,
                'dias_trabalhados' => $validated['dias_trabalhados'] ?? null,
                'dias_noturnos' => $validated['dias_noturnos'] ?? null,
                'valor' => $validated['valor'] ?? null,
                'valor_gratificacao' => $validated['valor_gratificacao'] ?? null,
                'porcentagem_insalubridade' => $validated['porcentagem_insalubridade'] ?? null,
                'porcentagem_periculosidade' => $validated['porcentagem_periculosidade'] ?? null,
                'adicional_turno' => $validated['adicional_turno'] ?? null,
                'adicional_noturno' => $validated['adicional_noturno'] ?? null,
                'observacao' => $validated['observacao'] ?? null,
                'status' => 'PENDENTE',
            ]);

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

        if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
            abort(403, 'Não autorizado.');
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
        StoreLancamentoSetorialRequest $request,
        LancamentoSetorial $lancamento,
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();

            if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
                abort(403, 'Não autorizado.');
            }

            $validated = $request->validated();

            $servidor = Servidor::findOrFail($validated['servidor_id']);
            $evento = EventoFolha::findOrFail($validated['evento_id']);

            $regrasService->validar($servidor, $evento, $validated);

            $lancamento->update([
                'servidor_id' => $validated['servidor_id'],
                'evento_id' => $validated['evento_id'],
                'dias_trabalhados' => $validated['dias_trabalhados'] ?? null,
                'dias_noturnos' => $validated['dias_noturnos'] ?? null,
                'valor' => $validated['valor'] ?? null,
                'valor_gratificacao' => $validated['valor_gratificacao'] ?? null,
                'porcentagem_insalubridade' => $validated['porcentagem_insalubridade'] ?? null,
                'porcentagem_periculosidade' => $validated['porcentagem_periculosidade'] ?? null,
                'adicional_turno' => $validated['adicional_turno'] ?? null,
                'adicional_noturno' => $validated['adicional_noturno'] ?? null,
                'observacao' => $validated['observacao'] ?? null,
            ]);

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
