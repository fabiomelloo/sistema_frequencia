<?php

namespace App\Http\Controllers;

use App\Enums\ConferenciaServidorStatus;
use App\Enums\EvidenciaStatus;
use App\Enums\FolhaFrequenciaStatus;
use App\Enums\FrequenciaServidorStatus;
use App\Http\Requests\ConferirFrequenciaServidorRequest;
use App\Http\Requests\DevolverFolhaFrequenciaRequest;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Models\Setor;
use App\Services\AuditService;
use App\Services\FolhaFrequenciaService;
use App\Services\NotificacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelFrequenciaController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', FolhaFrequenciaStatus::FINALIZADA->value)->toString();
        if (! in_array($status, array_column(FolhaFrequenciaStatus::cases(), 'value'), true)) {
            $status = FolhaFrequenciaStatus::FINALIZADA->value;
        }

        $query = FolhaFrequencia::query()
            ->where('status', $status)
            ->with(['setor', 'competencia', 'finalizadoPor', 'conferidoPor'])
            ->withCount(['servidores', 'servidores as pendentes_count' => fn ($items) => $items->where('status', FrequenciaServidorStatus::PENDENTE)]);

        if ($request->filled('competencia_id')) {
            $query->where('competencia_id', $request->integer('competencia_id'));
        }
        if ($request->filled('setor_id')) {
            $query->where('setor_id', $request->integer('setor_id'));
        }

        $contadores = FolhaFrequencia::query()
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('painel-frequencias.index', [
            'folhas' => $query->orderBy('finalizada_em')->paginate(15)->withQueryString(),
            'statusAtual' => FolhaFrequenciaStatus::from($status),
            'statusDisponiveis' => FolhaFrequenciaStatus::cases(),
            'contadores' => $contadores,
            'competencias' => Competencia::latest('data_inicio')->get(),
            'setores' => Setor::where('ativo', true)->orderBy('nome')->get(),
            'filtros' => $request->only(['competencia_id', 'setor_id']),
        ]);
    }

    public function show(FolhaFrequencia $folha): View
    {
        $this->authorize('conferir', $folha);
        $folha->load(['setor', 'competencia', 'servidores.itens.evidencias.enviadoPor', 'servidores.itens.evidencias.revisadoPor', 'servidores.conferencias.conferidoPor', 'finalizadoPor', 'conferidoPor']);
        $ocorrencias = OcorrenciaFrequencia::where('setor_id', $folha->setor_id)
            ->where('competencia_id', $folha->competencia_id)
            ->with(['dias', 'evidencias.enviadoPor', 'evidencias.revisadoPor'])->get()->groupBy('servidor_id');

        $conferenciasAtuais = $folha->servidores
            ->mapWithKeys(fn (FolhaFrequenciaServidor $item) => [$item->id => $item->conferenciaNaRodada($folha->rodada_conferencia)]);

        return view('painel-frequencias.show', [
            'folha' => $folha,
            'ocorrencias' => $ocorrencias,
            'conferenciasAtuais' => $conferenciasAtuais,
            'statusConferencia' => ConferenciaServidorStatus::cases(),
            'statusEvidencia' => [EvidenciaStatus::ACEITA, EvidenciaStatus::RECUSADA],
            'resumoConferencia' => [
                'total' => $folha->servidores->count(),
                'conferidos' => $conferenciasAtuais->filter(fn ($conferencia) => $conferencia?->status === ConferenciaServidorStatus::CONFERIDO)->count(),
                'divergentes' => $conferenciasAtuais->filter(fn ($conferencia) => $conferencia?->status === ConferenciaServidorStatus::DIVERGENTE)->count(),
                'pendentes' => $conferenciasAtuais->filter(fn ($conferencia) => $conferencia === null)->count(),
            ],
        ]);
    }

    public function conferirServidor(
        ConferirFrequenciaServidorRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaService $service,
    ): RedirectResponse {
        $this->authorize('conferir', $folha);
        abort_unless($item->folha_frequencia_id === $folha->id, 404);

        try {
            $conferencia = $service->conferirServidor(
                $folha,
                $item,
                ConferenciaServidorStatus::from($request->validated('status')),
                $request->validated('apontamento'),
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['conferencia' => $exception->getMessage()], "conferencia_{$item->id}");
        }

        AuditService::registrar(
            'CONFERIU_SERVIDOR_FREQUENCIA',
            'ConferenciaFrequenciaServidor',
            $conferencia->id,
            "{$item->nome}: {$conferencia->status->label()} na rodada {$conferencia->rodada}."
        );

        return back()->with('success', "Conferência de {$item->nome} registrada.");
    }

    public function aprovar(FolhaFrequencia $folha, FolhaFrequenciaService $service): RedirectResponse
    {
        $this->authorize('aprovar', $folha);
        $antes = $folha->toArray();

        try {
            $service->aprovar($folha, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['conferencia' => $e->getMessage()]);
        }

        AuditService::aprovou('FolhaFrequencia', $folha->id,
            "Frequência mensal aprovada: {$folha->setor->nome} / {$folha->competencia->descricao}",
            $antes, $folha->toArray());
        NotificacaoService::folhaFrequenciaAprovada($folha);

        return redirect()->route('painel-frequencias.index')->with('success', 'Frequência mensal aprovada.');
    }

    public function devolver(
        DevolverFolhaFrequenciaRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaService $service,
    ): RedirectResponse {
        $this->authorize('devolver', $folha);
        $antes = $folha->toArray();

        try {
            $service->devolver($folha, auth()->user(), $request->validated('motivo_devolucao'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['conferencia' => $e->getMessage()]);
        }

        AuditService::registrar('DEVOLVEU', 'FolhaFrequencia', $folha->id,
            "Frequência devolvida: {$folha->setor->nome}. Motivo: {$folha->motivo_devolucao}",
            $antes, $folha->toArray());
        NotificacaoService::folhaFrequenciaDevolvida($folha);

        return redirect()->route('painel-frequencias.index')->with('success', 'Frequência devolvida ao setor para correção.');
    }
}
