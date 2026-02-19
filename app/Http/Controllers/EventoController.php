<?php

namespace App\Http\Controllers;

use App\Models\EventoFolha;
use App\Models\Setor;
use App\Services\AuditService;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EventoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL,ADMIN');
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
        $evento = EventoFolha::create($request->validated());

        AuditService::criou('EventoFolha', $evento->id, 
            "Evento criado: {$evento->codigo_evento} — {$evento->descricao}",
            $evento->toArray()
        );

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
        $antes = $evento->toArray();
        $evento->update($request->validated());

        AuditService::editouComDiff('EventoFolha', $evento->id, $antes, $evento->fresh()->toArray(),
            "Evento atualizado: {$evento->codigo_evento}"
        );

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

        $dadosEvento = $evento->toArray();
        $evento->setoresComDireito()->detach();
        $evento->delete();

        AuditService::excluiu('EventoFolha', $dadosEvento['id'],
            "Evento excluído: {$dadosEvento['codigo_evento']} — {$dadosEvento['descricao']}",
            $dadosEvento
        );

        return redirect()
            ->route('admin.eventos.index')
            ->with('success', 'Evento deletado com sucesso!');
    }
}
