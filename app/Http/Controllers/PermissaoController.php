<?php

namespace App\Http\Controllers;

use App\Models\Setor;
use App\Models\EventoFolha;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PermissaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
    }

    public function index(): View
    {
        $setores = Setor::with('eventosPermitidos')
            ->orderBy('nome')
            ->get();

        $eventos = EventoFolha::where('ativo', true)
            ->orderBy('descricao')
            ->get();

        return view('admin.permissoes.index', [
            'setores' => $setores,
            'eventos' => $eventos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'setor_id' => ['required', 'exists:setores,id'],
            'evento_id' => ['required', 'exists:eventos_folha,id'],
        ]);

        $setor = Setor::findOrFail($validated['setor_id']);
        $evento = EventoFolha::findOrFail($validated['evento_id']);

        if ($setor->eventosPermitidos()->where('evento_id', $evento->id)->exists()) {
            return redirect()
                ->route('admin.permissoes.index')
                ->with('error', 'Esta permissão já existe.');
        }

        $setor->eventosPermitidos()->attach($evento->id, ['ativo' => true]);

        return redirect()
            ->route('admin.permissoes.index')
            ->with('success', 'Permissão concedida com sucesso!');
    }

    public function destroy(Request $request, Setor $setor, EventoFolha $evento): RedirectResponse
    {
        $setor->eventosPermitidos()->detach($evento->id);

        return redirect()
            ->route('admin.permissoes.index')
            ->with('success', 'Permissão removida com sucesso!');
    }

    public function toggle(Request $request, Setor $setor, EventoFolha $evento): RedirectResponse
    {
        $pivot = $setor->eventosPermitidos()->where('evento_id', $evento->id)->first();

        if ($pivot) {
            $setor->eventosPermitidos()->updateExistingPivot($evento->id, [
                'ativo' => !$pivot->pivot->ativo
            ]);
        }

        return redirect()
            ->route('admin.permissoes.index')
            ->with('success', 'Status da permissão alterado com sucesso!');
    }
}
