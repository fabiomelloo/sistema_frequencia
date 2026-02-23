<?php

namespace App\Http\Controllers;


use App\Models\LancamentoSetorial;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Competencia;
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
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            $servidor = Servidor::findOrFail($validated['servidor_id']);
            $evento = EventoFolha::findOrFail($validated['evento_id']);
            $competencia = $validated['competencia'];

            if (!Competencia::referenciaAberta($competencia)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['error' => "A competência {$competencia} está fechada para novos lançamentos."]);
            }

            if (LancamentoSetorial::existeDuplicata($servidor->id, $evento->id, $competencia)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['error' => "Já existe um lançamento para este servidor com este evento na competência {$competencia}."]);
            }

            $regrasService->validar($servidor, $evento, $validated, null, $user->setor_id);

            // Usa setor histórico do servidor na competência
            $setorOrigem = $servidor->setorNaCompetencia($competencia);

            $lancamento = LancamentoSetorial::create([
                'servidor_id' => $validated['servidor_id'],
                'evento_id' => $validated['evento_id'],
                'setor_origem_id' => $setorOrigem,
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

        // verificar setor OU delegação ativa
        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || \App\Models\Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        if (!$temAcesso || !$lancamento->podeSerEditado()) {
            abort(403, 'Não autorizado.');
        }

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
        RegrasLancamentoService $regrasService
    ): RedirectResponse {
        try {
            $user = auth()->user();

            // verificar setor OU delegação ativa
            $temAcesso = $lancamento->setor_origem_id === $user->setor_id
                || \App\Models\Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

            if (!$temAcesso || !$lancamento->podeSerEditado()) {
                abort(403, 'Não autorizado.');
            }

            // Limite de rejeições
            if ($lancamento->isRejeitado() && $lancamento->atingiuLimiteRejeicoes()) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => "Este lançamento atingiu o limite de rejeições e não pode mais ser re-submetido. Crie um novo lançamento."]);
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

            $regrasService->validar($servidor, $evento, $validated, $lancamento->id, $user->setor_id);

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

        // verificar setor OU delegação ativa
        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || \App\Models\Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        if (!$temAcesso || !$lancamento->podeSerEditado()) {
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

        // re-validar competência antes de restaurar
        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->route('lancamentos.lixeira')
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível restaurar.']);
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

        if (!Competencia::referenciaAberta($lancamento->competencia)) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'A competência deste lançamento está fechada. Não é possível conferir.']);
        }

        // impedir auto-aprovação (quem criou não pode conferir)
        $criadorId = \App\Models\AuditLog::where('modelo', 'LancamentoSetorial')
            ->where('modelo_id', $lancamento->id)
            ->where('acao', 'CRIOU')
            ->value('user_id');
        if ($criadorId && $criadorId === $user->id) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Você não pode conferir um lançamento que você mesmo criou. Peça a outro usuário do setor.']);
        }

        // Aceita PENDENTE e ESTORNADO para conferência setorial
        if (!$lancamento->isPendente() && !$lancamento->isEstornado()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Apenas lançamentos PENDENTES ou ESTORNADOS podem ser conferidos pelo setor.']);
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

    public function cancelar(LancamentoSetorial $lancamento): RedirectResponse
    {
        $user = auth()->user();

        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || \App\Models\Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        if (!$temAcesso || !$lancamento->podeSerCancelado()) {
            abort(403, 'Não autorizado ou lançamento não pode ser cancelado.');
        }

        $lancamento->status = LancamentoStatus::CANCELADO;
        $lancamento->save();

        AuditService::registrar('CANCELOU', 'LancamentoSetorial', $lancamento->id,
            "Lançamento cancelado pelo usuário: servidor_id={$lancamento->servidor_id}, evento_id={$lancamento->evento_id}"
        );

        return redirect()
            ->back()
            ->with('success', 'Lançamento cancelado de forma definitiva!');
    }

    public function solicitarEstorno(Request $request, LancamentoSetorial $lancamento): RedirectResponse
    {
        $user = auth()->user();

        $temAcesso = $lancamento->setor_origem_id === $user->setor_id
            || \App\Models\Delegacao::temDelegacaoAtiva($user->id, $lancamento->setor_origem_id);

        if (!$temAcesso || !$lancamento->podeSolicitarEstorno()) {
            abort(403, 'Não autorizado ou lançamento não pode ser estornado.');
        }

        $request->validate([
            'motivo_estorno' => 'required|string|min:5|max:1000'
        ]);

        $lancamento->status = LancamentoStatus::ESTORNO_SOLICITADO;
        $lancamento->motivo_rejeicao = $request->motivo_estorno; // We reuse motivo_rejeicao for the request reason
        $lancamento->save();

        AuditService::registrar('SOLICITOU_ESTORNO', 'LancamentoSetorial', $lancamento->id,
            "Solicitação de Estorno registrada: servidor_id={$lancamento->servidor_id}. Motivo: {$request->motivo_estorno}"
        );

        return redirect()
            ->back()
            ->with('success', 'Solicitação de estorno enviada para a Central!');
    }
}

