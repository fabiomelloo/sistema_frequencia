<?php

namespace App\Http\Controllers;

use App\Models\Setor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SetorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
    }

    public function index(): View
    {
        $setores = Setor::orderBy('nome')->paginate(20);

        return view('admin.setores.index', [
            'setores' => $setores,
        ]);
    }

    public function create(): View
    {
        return view('admin.setores.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:10'],
            'ativo' => ['nullable'],
        ]);

        Setor::create([
            'nome' => $validated['nome'],
            'sigla' => $validated['sigla'],
            'ativo' => $request->has('ativo'),
        ]);

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor criado com sucesso!');
    }

    public function show(Setor $setore): View
    {
        $setore->load(['usuarios', 'servidores']);

        // Carregar eventos permitidos sem filtro de ativo para visualização completa
        $eventosPermitidos = $setore->belongsToMany(\App\Models\EventoFolha::class, 'evento_setor', 'setor_id', 'evento_id')
            ->withPivot('ativo')
            ->get();

        return view('admin.setores.show', [
            'setor' => $setore,
            'eventosPermitidos' => $eventosPermitidos,
        ]);
    }

    public function edit(Setor $setore): View
    {
        return view('admin.setores.edit', [
            'setor' => $setore,
        ]);
    }

    public function update(Request $request, Setor $setore): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:10'],
            'ativo' => ['nullable'],
        ]);

        $setore->update([
            'nome' => $validated['nome'],
            'sigla' => $validated['sigla'],
            'ativo' => $request->has('ativo'),
        ]);

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor atualizado com sucesso!');
    }

    public function destroy(Setor $setore): RedirectResponse
    {
        if ($setore->usuarios()->count() > 0) {
            return redirect()
                ->route('admin.setores.index')
                ->with('error', 'Não é possível deletar um setor que possui usuários vinculados.');
        }

        if ($setore->servidores()->count() > 0) {
            return redirect()
                ->route('admin.setores.index')
                ->with('error', 'Não é possível deletar um setor que possui servidores vinculados.');
        }

        $setore->delete();

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor deletado com sucesso!');
    }
}
