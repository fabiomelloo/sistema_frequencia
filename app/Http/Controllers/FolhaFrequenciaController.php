<?php

namespace App\Http\Controllers;

use App\Enums\FrequenciaServidorStatus;
use App\Enums\OrigemInformacaoItem;
use App\Http\Requests\StoreFolhaFrequenciaRequest;
use App\Http\Requests\UpdateFrequenciaServidorRequest;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Services\AuditService;
use App\Services\FolhaFrequenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FolhaFrequenciaController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $folhas = FolhaFrequencia::where('setor_id', $user->setor_id)
            ->with('competencia')
            ->withCount([
                'servidores',
                'servidores as pendentes_count' => fn ($query) => $query->where('status', FrequenciaServidorStatus::PENDENTE),
            ])
            ->latest()->get();

        $competenciasUsadas = $folhas->pluck('competencia_id');
        $competenciasAbertas = Competencia::aberta()
            ->whereNotIn('id', $competenciasUsadas)
            ->latest('data_inicio')->get();

        return view('frequencia.index', compact('folhas', 'competenciasAbertas'));
    }

    public function store(StoreFolhaFrequenciaRequest $request, FolhaFrequenciaService $service): RedirectResponse
    {
        $competencia = Competencia::findOrFail($request->integer('competencia_id'));
        $folha = $service->criar($competencia, auth()->user());

        AuditService::criou('FolhaFrequencia', $folha->id,
            "Folha de frequência aberta para {$folha->setor->nome} / {$competencia->descricao}",
            ['servidores' => $folha->servidores()->count()]);

        return redirect()->route('frequencia.show', $folha)
            ->with('success', 'Relação da competência preparada para preenchimento.');
    }

    public function show(FolhaFrequencia $folha): View
    {
        $this->authorize('view', $folha);
        $folha->load(['setor', 'competencia', 'servidores.atualizadoPor', 'servidores.itens.evento', 'servidores.itens.evidencias.enviadoPor', 'servidores.itens.evidencias.revisadoPor', 'servidores.conferencias.conferidoPor', 'criadoPor', 'finalizadoPor', 'conferidoPor']);
        $ocorrencias = OcorrenciaFrequencia::where('setor_id', $folha->setor_id)
            ->where('competencia_id', $folha->competencia_id)
            ->with(['dias', 'evidencias.enviadoPor', 'evidencias.revisadoPor'])->get()->groupBy('servidor_id');

        return view('frequencia.show', [
            'folha' => $folha,
            'ocorrenciasPorServidor' => $ocorrencias,
            'statusFrequencia' => FrequenciaServidorStatus::cases(),
            'eventosMensais' => EventoFolha::query()
                ->where('ativo', true)
                ->where('regra_validada', true)
                ->where('origem_informacao', OrigemInformacaoItem::SETOR_MENSAL)
                ->whereHas('setoresComDireito', fn ($query) => $query->where('setores.id', $folha->setor_id))
                ->orderBy('descricao')
                ->get(),
            'resumo' => [
                'total' => $folha->servidores->count(),
                'pendentes' => $folha->servidores->where('status', FrequenciaServidorStatus::PENDENTE)->count(),
                'integrais' => $folha->servidores->where('status', FrequenciaServidorStatus::INTEGRAL)->count(),
                'com_faltas' => $folha->servidores->where('status', FrequenciaServidorStatus::COM_FALTAS)->count(),
            ],
        ]);
    }

    public function updateServidor(
        UpdateFrequenciaServidorRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaService $service,
    ): RedirectResponse {
        $this->authorize('update', $folha);
        abort_unless($item->folha_frequencia_id === $folha->id, 404);
        $antes = $item->toArray();

        try {
            $item = $service->atualizarServidor($item, $request->validated(), auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(["servidor_{$item->id}" => $e->getMessage()]);
        }

        AuditService::editou('FolhaFrequenciaServidor', $item->id,
            "Frequência atualizada: {$item->nome} / {$item->status->label()}", $antes, $item->toArray());

        return back()->with('success', "Frequência de {$item->nome} atualizada.");
    }

    public function finalizar(FolhaFrequencia $folha, FolhaFrequenciaService $service): RedirectResponse
    {
        $this->authorize('finalizar', $folha);

        try {
            $service->finalizar($folha, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['finalizacao' => $e->getMessage()]);
        }

        AuditService::registrar('FINALIZOU', 'FolhaFrequencia', $folha->id,
            "Folha de frequência finalizada: {$folha->competencia->descricao}");

        return back()->with('success', 'Frequência enviada para conferência da Central.');
    }

    public function reabrir(FolhaFrequencia $folha, FolhaFrequenciaService $service): RedirectResponse
    {
        $this->authorize('reabrir', $folha);

        try {
            $service->reabrir($folha);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reabertura' => $e->getMessage()]);
        }

        AuditService::registrar('REABRIU', 'FolhaFrequencia', $folha->id,
            "Folha de frequência reaberta: {$folha->competencia->descricao}");

        return back()->with('success', 'Folha reaberta para correção.');
    }
}
