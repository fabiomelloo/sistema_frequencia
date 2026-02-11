<?php

namespace App\Http\Controllers;

use App\Models\LancamentoSetorial;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Models\Setor;
use App\Http\Requests\StoreLancamentoSetorialRequest;
use App\Http\Requests\UpdateLancamentoSetorialRequest;
use App\Http\Requests\AprovarSetorialEmLoteRequest;
use App\Services\RegrasLancamentoService;
use App\Services\AuditService;
use App\Services\NotificacaoService;
use App\Enums\LancamentoStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class LancamentoSetorialController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = LancamentoSetorial::where('setor_origem_id', $user->setor_id)
            ->with(['servidor', 'evento', 'setorOrigem']);

        if ($request->filled('competencia')) {
            $query->where('competencia', $request->competencia);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', [LancamentoStatus::EXPORTADO->value]);
        }
        if ($request->filled('servidor_id')) {
            $query->where('servidor_id', $request->servidor_id);
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
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            $servidor = Servidor::findOrFail($validated['servidor_id']);
            $evento = EventoFolha::findOrFail($validated['evento_id']);
            $competencia = $validated['competencia'];

            if (LancamentoSetorial::existeDuplicata($servidor->id, $evento->id, $competencia)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['error' => "Já existe um lançamento para este servidor com este evento na competência {$competencia}."]);
            }

            $regrasService->validar($servidor, $evento, $validated);

            $lancamento = LancamentoSetorial::create([
                'servidor_id' => $validated['servidor_id'],
                'evento_id' => $validated['evento_id'],
                'setor_origem_id' => $user->setor_id,
                'competencia' => $competencia,
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

            $lancamento->status = LancamentoStatus::PENDENTE;
            $lancamento->save();

            AuditService::criou('LancamentoSetorial', $lancamento->id,
                "Lançamento criado: {$servidor->nome} - {$evento->descricao} ({$competencia})",
                $lancamento->toArray()
            );

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
        $user = auth()->user();

        if ($lancamento->setor_origem_id !== $user->setor_id) {
            abort(403, 'Não autorizado.');
        }

        $lancamento->load(['servidor', 'evento', 'setorOrigem', 'validador', 'conferidoSetorialPor']);

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
        UpdateLancamentoSetorialRequest $request,
        LancamentoSetorial $lancamento,
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();

            if ($lancamento->setor_origem_id !== $user->setor_id || !$lancamento->podeSerEditado()) {
                abort(403, 'Não autorizado.');
            }

            $validated = $request->validated();
            $dadosAntes = $lancamento->toArray();

            $servidor = Servidor::findOrFail($validated['servidor_id']);
            $evento = EventoFolha::findOrFail($validated['evento_id']);
            $competencia = $validated['competencia'] ?? $lancamento->competencia;

            if (LancamentoSetorial::existeDuplicata($servidor->id, $evento->id, $competencia, $lancamento->id)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['error' => "Já existe um lançamento para este servidor com este evento na competência {$competencia}."]);
            }

            $regrasService->validar($servidor, $evento, $validated, $lancamento->id);

            $lancamento->update([
                'servidor_id' => $validated['servidor_id'],
                'evento_id' => $validated['evento_id'],
                'competencia' => $competencia,
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

            if ($lancamento->isRejeitado()) {
                $lancamento->status = LancamentoStatus::PENDENTE;
                $lancamento->motivo_rejeicao = null;
                $lancamento->id_validador = null;
                $lancamento->validated_at = null;
                $lancamento->save();
            }

            AuditService::editou('LancamentoSetorial', $lancamento->id,
                "Lançamento editado: {$servidor->nome} - {$evento->descricao}",
                $dadosAntes, $lancamento->fresh()->toArray()
            );

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

        $dadosAntes = $lancamento->toArray();
        $lancamento->delete();

        AuditService::excluiu('LancamentoSetorial', $lancamento->id,
            "Lançamento excluído (lixeira): servidor_id={$lancamento->servidor_id}, evento_id={$lancamento->evento_id}",
            $dadosAntes
        );

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

    public function restaurar(int $id): RedirectResponse
    {
        $user = auth()->user();

        $lancamento = LancamentoSetorial::onlyTrashed()->findOrFail($id);

        if ($lancamento->setor_origem_id !== $user->setor_id) {
            abort(403, 'Não autorizado.');
        }

        $lancamento->restore();

        AuditService::registrar('RESTAUROU', 'LancamentoSetorial', $lancamento->id,
            "Lançamento restaurado da lixeira"
        );

        return redirect()
            ->route('lancamentos.lixeira')
            ->with('success', 'Lançamento restaurado com sucesso!');
    }

    public function aprovarSetorial(LancamentoSetorial $lancamento): RedirectResponse
    {
        $user = auth()->user();

        if ($lancamento->setor_origem_id !== $user->setor_id) {
            abort(403, 'Não autorizado.');
        }

        if (!$lancamento->isPendente()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos PENDENTES podem ser conferidos pelo setor.']);
        }

        $lancamento->status = LancamentoStatus::CONFERIDO_SETORIAL;
        $lancamento->conferido_setorial_por = $user->id;
        $lancamento->conferido_setorial_em = now();
        $lancamento->save();

        $lancamento->load(['servidor', 'evento']);

        AuditService::registrar('CONFERIU_SETORIAL', 'LancamentoSetorial', $lancamento->id,
            "Conferido pelo setor: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
        );

        return redirect()
            ->back()
            ->with('success', 'Lançamento conferido com sucesso! Aguarda aprovação da Central.');
    }

    public function aprovarSetorialEmLote(AprovarSetorialEmLoteRequest $request): RedirectResponse
    {

        $user = auth()->user();
        $aprovados = 0;

        foreach ($request->lancamento_ids as $id) {
            $lancamento = LancamentoSetorial::find($id);
            if ($lancamento && $lancamento->isPendente() && $lancamento->setor_origem_id === $user->setor_id) {
                $lancamento->status = LancamentoStatus::CONFERIDO_SETORIAL;
                $lancamento->conferido_setorial_por = $user->id;
                $lancamento->conferido_setorial_em = now();
                $lancamento->save();
                $aprovados++;
            }
        }

        return redirect()
            ->back()
            ->with('success', "{$aprovados} lançamento(s) conferido(s) pelo setor!");
    }
}
