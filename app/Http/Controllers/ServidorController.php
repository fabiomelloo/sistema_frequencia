<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use App\Models\Setor;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

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

        return redirect()
            ->route('admin.servidores.index')
            ->with('success', 'Servidor deletado com sucesso!');
    }
}
