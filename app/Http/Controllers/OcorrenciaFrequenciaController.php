<?php

namespace App\Http\Controllers;

use App\Enums\TipoOcorrenciaFrequencia;
use App\Http\Requests\SaveOcorrenciaFrequenciaRequest;
use App\Models\Competencia;
use App\Models\OcorrenciaFrequencia;
use App\Models\Servidor;
use App\Services\AuditService;
use App\Services\OcorrenciaFrequenciaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcorrenciaFrequenciaController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = OcorrenciaFrequencia::query()
            ->where('setor_id', $user->setor_id)
            ->with(['servidor', 'competencia', 'dias']);

        $query
            ->when($request->filled('competencia_id'), fn ($q) => $q->where('competencia_id', $request->integer('competencia_id')))
            ->when($request->filled('servidor_id'), fn ($q) => $q->where('servidor_id', $request->integer('servidor_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')));

        return view('ocorrencias.index', [
            'ocorrencias' => $query->latest()->paginate(15)->withQueryString(),
            'competencias' => Competencia::latest('data_inicio')->get(),
            'servidores' => $this->servidoresDoSetor(),
            'tipos' => TipoOcorrenciaFrequencia::cases(),
            'filtros' => $request->only(['competencia_id', 'servidor_id', 'tipo']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('ocorrencias.create', [
            ...$this->dadosFormulario(),
            'retorno' => $this->retornoSeguro($request->query('retorno')),
        ]);
    }

    public function store(SaveOcorrenciaFrequenciaRequest $request, OcorrenciaFrequenciaService $service): RedirectResponse
    {
        $ocorrencia = $service->criar($request->validated(), auth()->user());

        AuditService::criou('OcorrenciaFrequencia', $ocorrencia->id,
            "Ocorrência registrada para o servidor {$ocorrencia->servidor->nome}",
            $ocorrencia->load('dias')->toArray());

        $retorno = $this->retornoSeguro($request->validated('retorno'));
        if ($retorno) {
            return redirect()->to($retorno)
                ->with('success', 'Ocorrência registrada sem gerar lançamento financeiro.');
        }

        return redirect()->route('ocorrencias.show', $ocorrencia)
            ->with('success', 'Ocorrência registrada sem gerar lançamento financeiro.');
    }

    public function show(OcorrenciaFrequencia $ocorrencia): View
    {
        $this->authorize('view', $ocorrencia);

        return view('ocorrencias.show', [
            'ocorrencia' => $ocorrencia->load(['servidor', 'setor', 'competencia', 'dias', 'criadoPor', 'evidencias.enviadoPor', 'evidencias.revisadoPor']),
        ]);
    }

    public function edit(OcorrenciaFrequencia $ocorrencia): View
    {
        $this->authorize('update', $ocorrencia);

        return view('ocorrencias.edit', [
            ...$this->dadosFormulario(),
            'ocorrencia' => $ocorrencia->load('dias'),
            'retorno' => null,
        ]);
    }

    public function update(SaveOcorrenciaFrequenciaRequest $request, OcorrenciaFrequencia $ocorrencia, OcorrenciaFrequenciaService $service): RedirectResponse
    {
        $this->authorize('update', $ocorrencia);
        $antes = $ocorrencia->load('dias')->toArray();
        $ocorrencia = $service->atualizar($ocorrencia, $request->validated(), auth()->user());

        AuditService::editou('OcorrenciaFrequencia', $ocorrencia->id,
            "Ocorrência atualizada para o servidor {$ocorrencia->servidor->nome}",
            $antes, $ocorrencia->load('dias')->toArray());

        return redirect()->route('ocorrencias.show', $ocorrencia)
            ->with('success', 'Ocorrência atualizada com sucesso.');
    }

    public function destroy(OcorrenciaFrequencia $ocorrencia): RedirectResponse
    {
        $this->authorize('delete', $ocorrencia);
        $antes = $ocorrencia->load('dias')->toArray();
        $ocorrencia->delete();

        AuditService::excluiu('OcorrenciaFrequencia', $ocorrencia->id,
            "Ocorrência removida do servidor {$ocorrencia->servidor->nome}", $antes);

        return redirect()->route('ocorrencias.index')->with('success', 'Ocorrência removida.');
    }

    private function dadosFormulario(): array
    {
        return [
            'servidores' => $this->servidoresDoSetor(),
            'competencias' => Competencia::aberta()->latest('data_inicio')->get(),
            'tipos' => TipoOcorrenciaFrequencia::cases(),
        ];
    }

    private function servidoresDoSetor(): Collection
    {
        return Servidor::where('setor_id', auth()->user()->setor_id)
            ->where('ativo', true)->orderBy('nome')->get();
    }

    private function retornoSeguro(?string $retorno): ?string
    {
        if (! $retorno) {
            return null;
        }

        $destino = parse_url($retorno);
        $aplicacao = parse_url(url('/'));

        if (($destino['scheme'] ?? null) !== ($aplicacao['scheme'] ?? null)
            || ($destino['host'] ?? null) !== ($aplicacao['host'] ?? null)
            || ($destino['port'] ?? null) !== ($aplicacao['port'] ?? null)) {
            return null;
        }

        return $retorno;
    }
}
