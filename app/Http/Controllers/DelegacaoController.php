<?php

namespace App\Http\Controllers;

use App\Models\Delegacao;
use App\Models\User;
use App\Services\AuditService;
use App\Http\Requests\StoreDelegacaoRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DelegacaoController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $delegacoes = Delegacao::where('delegante_id', $user->id)
            ->orWhere('delegado_id', $user->id)
            ->with(['delegante', 'delegado', 'setor'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Usuários SETORIAIS disponíveis para receber delegação (exceto o próprio)
        $usuarios = User::where('role', \App\Enums\UserRole::SETORIAL->value)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('lancamentos.delegacoes', [
            'delegacoes' => $delegacoes,
            'usuariosDisponiveis' => $usuarios,
        ]);
    }

    public function store(StoreDelegacaoRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();
        $delegadoId = $validated['delegado_id'];

        $delegado = User::findOrFail($delegadoId);

        // Validar que o delegado é SETORIAL
        if (!$delegado->isSetorial()) {
            return redirect()->back()->withErrors(['error' => 'Permissão negada: O delegado deve ter o perfil SETORIAL.']);
        }

        // Segregação de Função: Prevenir delegação cruzada (A -> B e B -> A) simultaneamente
        $delegacaoCruzada = Delegacao::where('delegante_id', $delegadoId)
            ->where('delegado_id', $user->id)
            ->where('ativa', true)
            ->exists();

        if ($delegacaoCruzada) {
            return redirect()->back()->withErrors(['error' => 'Violação de Segregação: Este usuário já possui uma delegação ativa para você. Delegação cruzada não é permitida.']);
        }

        // Verificar se já existe delegação ativa
        $existe = Delegacao::where('delegante_id', $user->id)
            ->where('delegado_id', $delegadoId)
            ->where('ativa', true)
            ->exists();

        if ($existe) {
            return redirect()->back()->withErrors(['error' => 'Já existe uma delegação ativa para este usuário.']);
        }

        // Limite de delegações ativas por setor
        $limiteDelegacoes = (int) (\App\Models\Configuracao::get('limite_delegacoes_setor') ?? 3);
        $ativasNoSetor = Delegacao::where('setor_id', $user->setor_id)
            ->where('ativa', true)
            ->where('data_fim', '>=', now())
            ->count();

        if ($ativasNoSetor >= $limiteDelegacoes) {
            return redirect()->back()->withErrors([
                'error' => "Limite de {$limiteDelegacoes} delegações ativas por setor atingido. Revogue uma delegação existente antes de criar outra."
            ]);
        }

        // Limite de duração máxima (90 dias)
        $duracaoMaxima = (int) (\App\Models\Configuracao::get('duracao_maxima_delegacao_dias') ?? 90);
        $inicio = \Carbon\Carbon::parse($validated['data_inicio']);
        $fim = \Carbon\Carbon::parse($validated['data_fim']);
        $duracaoDias = $inicio->diffInDays($fim);

        if ($duracaoDias > $duracaoMaxima) {
            return redirect()->back()->withErrors([
                'error' => "A delegação não pode exceder {$duracaoMaxima} dias. Duração informada: {$duracaoDias} dias."
            ]);
        }

        $delegacao = Delegacao::create([
            'delegante_id' => $user->id,
            'delegado_id' => $delegadoId,
            'setor_id' => $user->setor_id,
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $validated['data_fim'],
            'ativa' => true,
            'motivo' => $validated['motivo'] ?? null,
        ]);

        AuditService::criou('Delegacao', $delegacao->id,
            "Delegação criada: {$user->name} → {$delegado->name} ({$request->data_inicio} a {$request->data_fim})"
        );

        return redirect()
            ->route('lancamentos.delegacoes.index')
            ->with('success', "Delegação para {$delegado->name} criada com sucesso!");
    }

    public function revogar(Delegacao $delegacao): RedirectResponse
    {
        $user = auth()->user();

        if ($delegacao->delegante_id !== $user->id) {
            abort(403, 'Apenas quem delegou pode revogar.');
        }

        $delegacao->ativa = false;
        $delegacao->save();

        AuditService::registrar('REVOGOU', 'Delegacao', $delegacao->id,
            "Delegação revogada"
        );

        return redirect()
            ->route('lancamentos.delegacoes.index')
            ->with('success', 'Delegação revogada com sucesso!');
    }
}
