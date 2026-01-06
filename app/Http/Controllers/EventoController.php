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
        try {
            // Preparar dados - converter strings vazias para null e checkboxes para boolean
            $data = $request->all();
            
            // Converter checkboxes para boolean (checkboxes HTML enviam "on" quando marcados)
            $data['exige_dias'] = $request->has('exige_dias') ? true : false;
            $data['exige_valor'] = $request->has('exige_valor') ? true : false;
            $data['exige_observacao'] = $request->has('exige_observacao') ? true : false;
            $data['exige_porcentagem'] = $request->has('exige_porcentagem') ? true : false;
            $data['ativo'] = $request->has('ativo') ? true : false;
            
            // Converter strings vazias para null
            $data['valor_minimo'] = $data['valor_minimo'] ?? null;
            $data['valor_maximo'] = $data['valor_maximo'] ?? null;
            $data['dias_maximo'] = $data['dias_maximo'] ?? null;
            
            if ($data['valor_minimo'] === '') {
                $data['valor_minimo'] = null;
            }
            if ($data['valor_maximo'] === '') {
                $data['valor_maximo'] = null;
            }
            if ($data['dias_maximo'] === '') {
                $data['dias_maximo'] = null;
            }

            $rules = [
                'codigo_evento' => ['required', 'string', 'max:20', 'unique:eventos_folha,codigo_evento'],
                'descricao' => ['required', 'string', 'max:255'],
                'exige_dias' => ['required', 'boolean'],
                'exige_valor' => ['required', 'boolean'],
                'valor_minimo' => ['nullable', 'numeric', 'min:0'],
                'valor_maximo' => ['nullable', 'numeric', 'min:0'],
                'exige_observacao' => ['required', 'boolean'],
                'exige_porcentagem' => ['required', 'boolean'],
                'ativo' => ['required', 'boolean'],
            ];

            // Validar dias_maximo apenas se não for null
            if ($data['dias_maximo'] !== null) {
                $rules['dias_maximo'] = ['integer', 'min:1'];
            } else {
                $rules['dias_maximo'] = ['nullable'];
            }

            $validated = validator($data, $rules, [
                'codigo_evento.required' => 'O código do evento é obrigatório.',
                'codigo_evento.unique' => 'Este código de evento já está em uso.',
                'descricao.required' => 'A descrição é obrigatória.',
                'valor_minimo.numeric' => 'O valor mínimo deve ser um número.',
                'valor_maximo.numeric' => 'O valor máximo deve ser um número.',
                'dias_maximo.integer' => 'O dias máximo deve ser um número inteiro.',
                'dias_maximo.min' => 'O dias máximo deve ser pelo menos 1.',
            ])->validate();

            // Converter strings vazias para null
            $valorMinimo = !empty($validated['valor_minimo']) ? $validated['valor_minimo'] : null;
            $valorMaximo = !empty($validated['valor_maximo']) ? $validated['valor_maximo'] : null;
            $diasMaximo = !empty($validated['dias_maximo']) ? $validated['dias_maximo'] : null;

            // Validação customizada: valor_maximo deve ser maior que valor_minimo quando ambos existem
            if ($valorMaximo !== null && $valorMinimo !== null) {
                if ($valorMaximo <= $valorMinimo) {
                    return redirect()
                        ->back()
                        ->withInput()
                        ->withErrors(['valor_maximo' => 'O valor máximo deve ser maior que o valor mínimo.']);
                }
            }

            $evento = EventoFolha::create([
                'codigo_evento' => $validated['codigo_evento'],
                'descricao' => $validated['descricao'],
                'exige_dias' => $validated['exige_dias'],
                'exige_valor' => $validated['exige_valor'],
                'valor_minimo' => $valorMinimo,
                'valor_maximo' => $valorMaximo,
                'dias_maximo' => $diasMaximo,
                'exige_observacao' => $validated['exige_observacao'],
                'exige_porcentagem' => $validated['exige_porcentagem'],
                'ativo' => $validated['ativo'],
            ]);

            return redirect()
                ->route('admin.eventos.index')
                ->with('success', 'Evento criado com sucesso!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Erro ao criar evento: ' . $e->getMessage()]);
        }
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
        // Preparar dados - converter checkboxes para boolean
        $data = $request->all();
        
        // Converter checkboxes para boolean
        $data['exige_dias'] = $request->has('exige_dias') ? true : false;
        $data['exige_valor'] = $request->has('exige_valor') ? true : false;
        $data['exige_observacao'] = $request->has('exige_observacao') ? true : false;
        $data['exige_porcentagem'] = $request->has('exige_porcentagem') ? true : false;
        $data['ativo'] = $request->has('ativo') ? true : false;
        
        // Converter strings vazias para null
        if (isset($data['valor_minimo']) && $data['valor_minimo'] === '') {
            $data['valor_minimo'] = null;
        }
        if (isset($data['valor_maximo']) && $data['valor_maximo'] === '') {
            $data['valor_maximo'] = null;
        }
        if (isset($data['dias_maximo']) && $data['dias_maximo'] === '') {
            $data['dias_maximo'] = null;
        }

        $rules = [
            'codigo_evento' => ['required', 'string', 'max:20', 'unique:eventos_folha,codigo_evento,' . $evento->id],
            'descricao' => ['required', 'string', 'max:255'],
            'exige_dias' => ['required', 'boolean'],
            'exige_valor' => ['required', 'boolean'],
            'valor_minimo' => ['nullable', 'numeric', 'min:0'],
            'valor_maximo' => ['nullable', 'numeric', 'min:0'],
            'exige_observacao' => ['required', 'boolean'],
            'exige_porcentagem' => ['required', 'boolean'],
            'ativo' => ['required', 'boolean'],
        ];

        // Validar dias_maximo apenas se não for null
        if ($data['dias_maximo'] !== null) {
            $rules['dias_maximo'] = ['integer', 'min:1'];
        } else {
            $rules['dias_maximo'] = ['nullable'];
        }

        $validated = validator($data, $rules)->validate();

        // Converter strings vazias para null
        $valorMinimo = !empty($validated['valor_minimo']) ? $validated['valor_minimo'] : null;
        $valorMaximo = !empty($validated['valor_maximo']) ? $validated['valor_maximo'] : null;
        $diasMaximo = !empty($validated['dias_maximo']) ? $validated['dias_maximo'] : null;

        // Validação customizada: valor_maximo deve ser maior que valor_minimo quando ambos existem
        if ($valorMaximo !== null && $valorMinimo !== null) {
            if ($valorMaximo <= $valorMinimo) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['valor_maximo' => 'O valor máximo deve ser maior que o valor mínimo.']);
            }
        }

        $evento->update([
            'codigo_evento' => $validated['codigo_evento'],
            'descricao' => $validated['descricao'],
            'exige_dias' => $validated['exige_dias'],
            'exige_valor' => $validated['exige_valor'],
            'valor_minimo' => $valorMinimo,
            'valor_maximo' => $valorMaximo,
            'dias_maximo' => $diasMaximo,
            'exige_observacao' => $validated['exige_observacao'],
            'exige_porcentagem' => $validated['exige_porcentagem'],
            'ativo' => $validated['ativo'],
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
