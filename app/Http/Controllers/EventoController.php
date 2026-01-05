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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_evento' => ['required', 'string', 'max:20', 'unique:eventos_folha,codigo_evento'],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['boolean'],
            'exige_valor' => ['boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => ['nullable', 'numeric', 'min:0', 'gt:valor_minimo'],
            'dias_maximo' => ['nullable', 'integer', 'min:1'],
            'exige_observacao' => ['boolean'],
            'exige_porcentagem' => ['boolean'],
            'ativo' => ['boolean'],
        ]);

        EventoFolha::create([
            'codigo_evento' => $validated['codigo_evento'],
            'descricao' => $validated['descricao'],
            'exige_dias' => $request->has('exige_dias'),
            'exige_valor' => $request->has('exige_valor'),
            'valor_minimo' => $validated['valor_minimo'] ?? null,
            'valor_maximo' => $validated['valor_maximo'] ?? null,
            'dias_maximo' => $validated['dias_maximo'] ?? null,
            'exige_observacao' => $request->has('exige_observacao'),
            'exige_porcentagem' => $request->has('exige_porcentagem'),
            'ativo' => $request->has('ativo'),
        ]);

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

    public function update(Request $request, EventoFolha $evento): RedirectResponse
    {
        $validated = $request->validate([
            'codigo_evento' => ['required', 'string', 'max:20', 'unique:eventos_folha,codigo_evento,' . $evento->id],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['boolean'],
            'exige_valor' => ['boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => ['nullable', 'numeric', 'min:0', 'gt:valor_minimo'],
            'dias_maximo' => ['nullable', 'integer', 'min:1'],
            'exige_observacao' => ['boolean'],
            'exige_porcentagem' => ['boolean'],
            'ativo' => ['boolean'],
        ]);

        $evento->update([
            'codigo_evento' => $validated['codigo_evento'],
            'descricao' => $validated['descricao'],
            'exige_dias' => $request->has('exige_dias'),
            'exige_valor' => $request->has('exige_valor'),
            'valor_minimo' => $validated['valor_minimo'] ?? null,
            'valor_maximo' => $validated['valor_maximo'] ?? null,
            'dias_maximo' => $validated['dias_maximo'] ?? null,
            'exige_observacao' => $request->has('exige_observacao'),
            'exige_porcentagem' => $request->has('exige_porcentagem'),
            'ativo' => $request->has('ativo'),
        ]);

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
