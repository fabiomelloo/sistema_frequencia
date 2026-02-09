<?php

namespace App\Http\Controllers;

use App\Models\EventoFolha;
use App\Models\Setor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EventoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL');
    }

    public function index(): View
    {
        $eventos = EventoFolha::orderBy('descricao')->paginate(20);

        return view('admin.eventos.index', [
            'eventos' => $eventos,
        ]);
    }

    public function create(): View
    {
        return view('admin.eventos.create');
    }

    public function store(\App\Http\Requests\StoreEventoRequest $request): RedirectResponse
    {
        EventoFolha::create($request->validated());

        return redirect()
            ->route('admin.eventos.index')
            ->with('success', 'Evento criado com sucesso!');
    }
    
    public function show(EventoFolha $evento): View
    {
        $evento->load('setoresComDireito');

        return view('admin.eventos.show', [
            'evento' => $evento,
        ]);
    }

    public function edit(EventoFolha $evento): View
    {
        return view('admin.eventos.edit', [
            'evento' => $evento,
        ]);
    }

    public function update(\App\Http\Requests\UpdateEventoRequest $request, EventoFolha $evento): RedirectResponse
    {
        $evento->update($request->validated());

        return redirect()
            ->route('admin.eventos.index')
            ->with('success', 'Evento atualizado com sucesso!');
    }

    public function destroy(EventoFolha $evento): RedirectResponse
    {
        if ($evento->lancamentos()->count() > 0) {
            return redirect()
                ->route('admin.eventos.index')
                ->with('error', 'Não é possível deletar um evento que possui lançamentos vinculados.');
        }

        $evento->setoresComDireito()->detach();
        $evento->delete();

        return redirect()
            ->route('admin.eventos.index')
            ->with('success', 'Evento deletado com sucesso!');
    }
}
