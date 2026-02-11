<?php

namespace App\Http\Controllers;

use App\Services\ImportacaoService;
use App\Services\AuditService;
use App\Http\Requests\ImportacaoRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ImportacaoController extends Controller
{
    public function form(): View
    {
        return view('lancamentos.importar');
    }

    public function importar(ImportacaoRequest $request, ImportacaoService $service): RedirectResponse
    {

        $user = auth()->user();
        
        try {
            $resultado = $service->importarCsv($request->file('arquivo'), $user->setor_id);

            AuditService::criou('Importacao', null,
                "Importação CSV: {$resultado['importados']} lançamentos importados de {$resultado['total_linhas']} linhas"
            );

            $mensagem = "{$resultado['importados']} lançamento(s) importado(s) com sucesso!";
            
            if (!empty($resultado['erros'])) {
                $mensagem .= " Houve " . count($resultado['erros']) . " erro(s).";
                return redirect()
                    ->route('lancamentos.importar.form')
                    ->with('success', $mensagem)
                    ->with('erros_importacao', $resultado['erros']);
            }

            return redirect()
                ->route('lancamentos.index')
                ->with('success', $mensagem);

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Erro na importação: ' . $e->getMessage()]);
        }
    }
}
