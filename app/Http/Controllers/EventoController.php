<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Models\EventoFolha;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL|ADMIN');
    }

    public function index(): View
    {
        $this->authorize('viewAny', EventoFolha::class);

        $eventos = EventoFolha::orderBy('descricao')->paginate(20);

        return view('admin.eventos.index', [
            'eventos' => $eventos,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EventoFolha::class);

        return view('admin.eventos.create');
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        $evento = EventoFolha::create($this->dadosValidados($request->validated()));

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
        $this->authorize('view', $evento);

        $evento->load('setoresComDireito');

        return view('admin.eventos.show', [
            'evento' => $evento,
        ]);
    }

    public function edit(EventoFolha $evento): View
    {
        $this->authorize('update', $evento);

        return view('admin.eventos.edit', [
            'evento' => $evento,
        ]);
    }

    public function update(UpdateEventoRequest $request, EventoFolha $evento): RedirectResponse
    {
        $antes = $evento->toArray();
        $evento->update($this->dadosValidados($request->validated()));

        AuditService::editouComDiff('EventoFolha', $evento->id, $antes, $evento->fresh()->toArray(),
            "Evento atualizado: {$evento->codigo_evento}"
        );

        return redirect()
            ->route('admin.eventos.index')
            ->with('success', 'Evento atualizado com sucesso!');
    }

    public function destroy(EventoFolha $evento): RedirectResponse
    {
        $this->authorize('delete', $evento);

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

    /** @param array<string, mixed> $dados */
    private function dadosValidados(array $dados): array
    {
        if ($dados['regra_validada']) {
            $dados['regra_validada_por_id'] = auth()->id();
            $dados['regra_validada_em'] = now();
        } else {
            $dados['regra_validada_por_id'] = null;
            $dados['regra_validada_em'] = null;
        }

        return $dados;
    }
}
