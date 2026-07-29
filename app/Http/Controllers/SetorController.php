<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSetorRequest;
use App\Http\Requests\UpdateSetorRequest;
use App\Models\EventoFolha;
use App\Models\Setor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SetorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL|ADMIN');
    }

    public function index(): View
    {
        $this->authorize('viewAny', Setor::class);

        $setores = Setor::with('setorPai')->orderBy('nome')->paginate(20);

        return view('admin.setores.index', [
            'setores' => $setores,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Setor::class);

        return view('admin.setores.create', [
            'setoresPai' => Setor::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    public function store(StoreSetorRequest $request): RedirectResponse
    {
        Setor::create($request->validated());

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor criado com sucesso!');
    }

    public function show(Setor $setor): View
    {
        $this->authorize('view', $setor);

        $setor->load(['usuarios', 'servidores', 'setorPai', 'setoresFilhos']);

        // Carregar eventos permitidos sem filtro de ativo para visualização completa
        $eventosPermitidos = $setor->belongsToMany(EventoFolha::class, 'evento_setor', 'setor_id', 'evento_id')
            ->withPivot('ativo')
            ->get();

        return view('admin.setores.show', [
            'setor' => $setor,
            'eventosPermitidos' => $eventosPermitidos,
        ]);
    }

    public function edit(Setor $setor): View
    {
        $this->authorize('update', $setor);

        return view('admin.setores.edit', [
            'setor' => $setor,
            'setoresPai' => Setor::where('id', '!=', $setor->id)->orderBy('nome')->get(),
        ]);
    }

    public function update(UpdateSetorRequest $request, Setor $setor): RedirectResponse
    {
        $setor->update($request->validated());

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor atualizado com sucesso!');
    }

    public function destroy(Setor $setor): RedirectResponse
    {
        $this->authorize('delete', $setor);

        if ($setor->usuarios()->count() > 0) {
            return redirect()
                ->route('admin.setores.index')
                ->with('error', 'Não é possível deletar um setor que possui usuários vinculados.');
        }

        if ($setor->servidores()->count() > 0) {
            return redirect()
                ->route('admin.setores.index')
                ->with('error', 'Não é possível deletar um setor que possui servidores vinculados.');
        }

        if ($setor->setoresFilhos()->exists()) {
            return redirect()
                ->route('admin.setores.index')
                ->with('error', 'Não é possível deletar um setor que possui setores subordinados.');
        }

        $setor->delete();

        return redirect()
            ->route('admin.setores.index')
            ->with('success', 'Setor deletado com sucesso!');
    }
}
