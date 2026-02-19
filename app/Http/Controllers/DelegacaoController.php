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

        // Usuários do mesmo setor para delegar (exceto o próprio)
        $usuarios = User::where('setor_id', $user->setor_id)
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

        // Validar que o delegado é do mesmo setor
        $delegado = User::findOrFail($delegadoId);
        if ($delegado->setor_id !== $user->setor_id) {
            return redirect()->back()->withErrors(['error' => 'O delegado deve ser do mesmo setor.']);
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
