<?php

namespace App\Http\Controllers;

use App\Enums\OrigemInformacaoItem;
use App\Enums\TipoDesignacaoFuncional;
use App\Enums\VinculoServidor;
use App\Http\Requests\EncerrarVigenciaFuncionalRequest;
use App\Http\Requests\StoreDesignacaoFuncionalRequest;
use App\Http\Requests\StoreVantagemFuncionalRequest;
use App\Http\Requests\StoreVinculoFuncionalRequest;
use App\Models\DesignacaoFuncional;
use App\Models\EventoFolha;
use App\Models\Servidor;
use App\Models\VantagemFuncional;
use App\Services\HistoricoFuncionalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class HistoricoFuncionalController extends Controller
{
    public function __construct(private readonly HistoricoFuncionalService $service)
    {
        $this->middleware('auth');
        $this->middleware('role:CENTRAL|ADMIN');
    }

    public function show(Servidor $servidor): View
    {
        $this->authorize('view', $servidor);

        $servidor->load([
            'vinculosFuncionais.registradoPor',
            'designacoesFuncionais.registradoPor',
            'vantagensFuncionais.evento',
            'vantagensFuncionais.registradoPor',
        ]);

        $eventos = EventoFolha::query()
            ->where('ativo', true)
            ->where('regra_validada', true)
            ->where('origem_informacao', OrigemInformacaoItem::CADASTRO_FUNCIONAL)
            ->orderBy('descricao')
            ->get();

        return view('admin.servidores.historico-funcional', [
            'servidor' => $servidor,
            'eventos' => $eventos,
            'tiposVinculo' => VinculoServidor::cases(),
            'tiposDesignacao' => TipoDesignacaoFuncional::cases(),
        ]);
    }

    public function storeVinculo(StoreVinculoFuncionalRequest $request, Servidor $servidor): RedirectResponse
    {
        return $this->executar(function () use ($request, $servidor): void {
            $this->service->registrarVinculo($servidor, $request->validated(), $request->user()->id);
        }, $servidor, 'Nova vigência funcional registrada.');
    }

    public function storeDesignacao(StoreDesignacaoFuncionalRequest $request, Servidor $servidor): RedirectResponse
    {
        return $this->executar(function () use ($request, $servidor): void {
            $this->service->registrarDesignacao($servidor, $request->validated(), $request->user()->id);
        }, $servidor, 'Designação funcional registrada.');
    }

    public function storeVantagem(StoreVantagemFuncionalRequest $request, Servidor $servidor): RedirectResponse
    {
        return $this->executar(function () use ($request, $servidor): void {
            $dados = $request->dadosParaPersistencia();
            $evento = EventoFolha::findOrFail($dados['evento_id']);
            unset($dados['evento_id']);
            $this->service->registrarVantagem($servidor, $evento, $dados, $request->user()->id);
        }, $servidor, 'Vantagem funcional registrada.');
    }

    public function encerrarDesignacao(
        EncerrarVigenciaFuncionalRequest $request,
        Servidor $servidor,
        DesignacaoFuncional $designacao
    ): RedirectResponse {
        return $this->executar(function () use ($request, $servidor, $designacao): void {
            $this->service->encerrar($designacao, Carbon::parse($request->validated('data_fim')), $servidor);
        }, $servidor, 'Designação encerrada.');
    }

    public function encerrarVantagem(
        EncerrarVigenciaFuncionalRequest $request,
        Servidor $servidor,
        VantagemFuncional $vantagem
    ): RedirectResponse {
        return $this->executar(function () use ($request, $servidor, $vantagem): void {
            $this->service->encerrar($vantagem, Carbon::parse($request->validated('data_fim')), $servidor);
        }, $servidor, 'Vantagem encerrada.');
    }

    private function executar(callable $acao, Servidor $servidor, string $mensagem): RedirectResponse
    {
        try {
            $acao();

            return redirect()->route('admin.servidores.historico', $servidor)->with('success', $mensagem);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['vigencia' => $exception->getMessage()]);
        }
    }
}
