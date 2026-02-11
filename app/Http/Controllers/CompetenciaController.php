<?php

namespace App\Http\Controllers;

use App\Models\Competencia;
use App\Services\CompetenciaService;
use App\Services\AuditService;
use App\Http\Requests\StoreCompetenciaRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CompetenciaController extends Controller
{
    public function index(): View
    {
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
                "Competência {$competencia->referencia} aberta" .
                ($competencia->data_limite ? " com prazo até {$competencia->data_limite->format('d/m/Y')}" : '')
            );

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', "Competência {$competencia->referencia} aberta com sucesso!");

        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function fechar(Competencia $competencia, CompetenciaService $service): RedirectResponse
    {
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
