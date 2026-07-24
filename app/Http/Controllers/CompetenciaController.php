<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompetenciaRequest;
use App\Models\Competencia;
use App\Services\AuditService;
use App\Services\CoberturaFrequenciaService;
use App\Services\CompetenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompetenciaController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Competencia::class);

        $competencias = Competencia::orderBy('referencia', 'desc')->paginate(12);

        return view('admin.competencias.index', [
            'competencias' => $competencias,
        ]);
    }

    public function store(StoreCompetenciaRequest $request, CompetenciaService $service): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $competencia = $service->abrir($validated['referencia'], $validated['data_limite'] ?? null);

            AuditService::criou('Competencia', $competencia->id,
                "Competência {$competencia->referencia} aberta".
                ($competencia->data_limite ? " com prazo até {$competencia->data_limite->format('d/m/Y')}" : '')
            );

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', "Competência {$competencia->referencia} aberta com sucesso!");

        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cobertura(Competencia $competencia, CoberturaFrequenciaService $service): View
    {
        $this->authorize('view', $competencia);
        $cobertura = $service->porCompetencia($competencia);

        return view('admin.competencias.cobertura', [
            'competencia' => $competencia,
            'cobertura' => $cobertura,
            'resumo' => $service->resumo($competencia, $cobertura),
        ]);
    }

    public function fechar(Competencia $competencia, CompetenciaService $service): RedirectResponse
    {
        $this->authorize('update', $competencia);

        try {
            $service->fechar($competencia);

            AuditService::registrar('FECHOU', 'Competencia', $competencia->id,
                "Competência {$competencia->referencia} fechada"
            );

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', "Competência {$competencia->referencia} fechada com sucesso!");

        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reabrir(Competencia $competencia, CompetenciaService $service): RedirectResponse
    {
        $this->authorize('update', $competencia);

        try {
            $service->abrir($competencia->referencia, $competencia->data_limite);

            AuditService::registrar('REABRIU', 'Competencia', $competencia->id,
                "Competência {$competencia->referencia} reaberta"
            );

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', "Competência {$competencia->referencia} reaberta com sucesso!");

        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
