<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use App\Models\Setor;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'matricula' => ['required', 'string', 'max:50', 'unique:servidores,matricula'],
            'nome' => ['required', 'string', 'max:255'],
            'setor_id' => ['required', 'exists:setores,id'],
            'origem_registro' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable'],
        ]);

        Servidor::create([
            'matricula' => $validated['matricula'],
            'nome' => $validated['nome'],
            'setor_id' => $validated['setor_id'],
            'origem_registro' => $validated['origem_registro'] ?? null,
            'ativo' => $request['ativo'],
        ]);

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

    public function update(Request $request, Servidor $servidor): RedirectResponse
    {
        $validated = $request->validate([
            'matricula' => ['required', 'string', 'max:50', 'unique:servidores,matricula,' . $servidor->id],
            'nome' => ['required', 'string', 'max:255'],
            'setor_id' => ['required', 'exists:setores,id'],
            'origem_registro' => ['nullable', 'string', 'max:255'],
            'ativo' => ['nullable'],
        ]);

        $servidor->update([
            'matricula' => $validated['matricula'],
            'nome' => $validated['nome'],
            'setor_id' => $validated['setor_id'],
            'origem_registro' => $validated['origem_registro'] ?? null,
            'ativo' => $request['ativo'],
        ]);

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
