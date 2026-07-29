<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfiguracoesRequest;
use App\Services\ConfiguracaoSistemaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfiguracaoController extends Controller
{
    public function index(ConfiguracaoSistemaService $service): View
    {
        return view('admin.configuracoes.index', [
            'configuracoes' => $service->configuracoesParaEdicao(),
        ]);
    }

    public function update(
        UpdateConfiguracoesRequest $request,
        ConfiguracaoSistemaService $service
    ): RedirectResponse {
        $atualizadas = $service->atualizar($request->validated());

        return redirect()
            ->route('admin.configuracoes.index')
            ->with('success', "{$atualizadas} configuração(ões) atualizada(s) com sucesso!");
    }
}
