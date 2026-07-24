<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFolhaFrequenciaItemRequest;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Services\AuditService;
use App\Services\FolhaFrequenciaItemService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class FolhaFrequenciaItemController extends Controller
{
    public function store(
        SaveFolhaFrequenciaItemRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaItemService $service,
    ): RedirectResponse {
        $this->authorize('update', $folha);
        $this->garantirPertencimento($folha, $item);

        try {
            $registro = $service->criar($item, $request->evento(), $request->dadosDoConteudo(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['vigencia' => $exception->getMessage()], "item_{$item->id}");
        }

        AuditService::criou('FolhaFrequenciaItem', $registro->id, "Item {$registro->codigo_evento} lançado para {$item->nome}.");

        return back()->with('success', "Item mensal registrado para {$item->nome}.");
    }

    public function update(
        SaveFolhaFrequenciaItemRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaItem $frequenciaItem,
        FolhaFrequenciaItemService $service,
    ): RedirectResponse {
        $this->authorize('update', $folha);
        $this->garantirPertencimento($folha, $item, $frequenciaItem);
        $antes = $frequenciaItem->toArray();

        try {
            $registro = $service->atualizar($frequenciaItem, $request->dadosDoConteudo(), $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['vigencia' => $exception->getMessage()], "item_{$item->id}");
        }

        AuditService::editou('FolhaFrequenciaItem', $registro->id, "Item {$registro->codigo_evento} atualizado para {$item->nome}.", $antes, $registro->toArray());

        return back()->with('success', "Item mensal de {$item->nome} atualizado.");
    }

    public function destroy(
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaItem $frequenciaItem,
        FolhaFrequenciaItemService $service,
    ): RedirectResponse {
        $this->authorize('update', $folha);
        $this->garantirPertencimento($folha, $item, $frequenciaItem);
        $dados = $frequenciaItem->toArray();

        try {
            $service->remover($frequenciaItem);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['vigencia' => $exception->getMessage()], "item_{$item->id}");
        }

        AuditService::excluiu('FolhaFrequenciaItem', $frequenciaItem->id, "Item {$frequenciaItem->codigo_evento} removido de {$item->nome}.", $dados);

        return back()->with('success', "Item mensal removido de {$item->nome}.");
    }

    private function garantirPertencimento(
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        ?FolhaFrequenciaItem $frequenciaItem = null,
    ): void {
        abort_unless($item->folha_frequencia_id === $folha->id, 404);
        if ($frequenciaItem) {
            abort_unless($frequenciaItem->folha_frequencia_servidor_id === $item->id, 404);
        }
    }
}
