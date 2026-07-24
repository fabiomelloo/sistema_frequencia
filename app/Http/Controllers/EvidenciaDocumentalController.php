<?php

namespace App\Http\Controllers;

use App\Enums\EvidenciaStatus;
use App\Http\Requests\RevisarEvidenciaDocumentalRequest;
use App\Http\Requests\StoreEvidenciaDocumentalRequest;
use App\Models\EvidenciaDocumental;
use App\Models\FolhaFrequencia;
use App\Models\FolhaFrequenciaItem;
use App\Models\FolhaFrequenciaServidor;
use App\Models\OcorrenciaFrequencia;
use App\Services\AuditService;
use App\Services\EvidenciaDocumentalService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenciaDocumentalController extends Controller
{
    public function storeOcorrencia(
        StoreEvidenciaDocumentalRequest $request,
        OcorrenciaFrequencia $ocorrencia,
        EvidenciaDocumentalService $service,
    ): RedirectResponse {
        $this->authorize('update', $ocorrencia);

        try {
            $evidencia = $service->armazenar($ocorrencia, $request->file('arquivo'), $request->validated('descricao'), $request->user());
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return back()->withInput()->withErrors(['arquivo' => $exception->getMessage()], $request->errorBagName());
        }

        AuditService::criou('EvidenciaDocumental', $evidencia->id, "Documento anexado à ocorrência {$ocorrencia->id}.", $this->dadosAuditoria($evidencia));

        return back()->with('success', 'Documento comprobatório anexado com segurança.');
    }

    public function storeItem(
        StoreEvidenciaDocumentalRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        FolhaFrequenciaItem $frequenciaItem,
        EvidenciaDocumentalService $service,
    ): RedirectResponse {
        $this->authorize('update', $folha);
        abort_unless($item->folha_frequencia_id === $folha->id, 404);
        abort_unless($frequenciaItem->folha_frequencia_servidor_id === $item->id, 404);

        try {
            $evidencia = $service->armazenar($frequenciaItem, $request->file('arquivo'), $request->validated('descricao'), $request->user());
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return back()->withInput()->withErrors(['arquivo' => $exception->getMessage()], $request->errorBagName());
        }

        AuditService::criou('EvidenciaDocumental', $evidencia->id, "Documento anexado ao item {$frequenciaItem->codigo_evento} de {$item->nome}.", $this->dadosAuditoria($evidencia));

        return back()->with('success', "Documento anexado ao item de {$item->nome}.");
    }

    public function download(EvidenciaDocumental $evidencia, EvidenciaDocumentalService $service): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $evidencia);

        try {
            $caminho = $service->caminhoIntegro($evidencia);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['evidencia' => $exception->getMessage()]);
        }

        AuditService::leu('EvidenciaDocumental', $evidencia->id, 'Documento comprobatório acessado em armazenamento privado.');

        return response()->download($caminho, $evidencia->nome_original, [
            'Content-Type' => $evidencia->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(EvidenciaDocumental $evidencia, EvidenciaDocumentalService $service): RedirectResponse
    {
        $this->authorize('delete', $evidencia);
        $dados = $this->dadosAuditoria($evidencia);

        try {
            $service->remover($evidencia, auth()->user());
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return back()->withErrors(['evidencia' => $exception->getMessage()]);
        }

        AuditService::excluiu('EvidenciaDocumental', $evidencia->id, 'Documento comprobatório removido do armazenamento privado.', $dados);

        return back()->with('success', 'Documento removido do armazenamento privado.');
    }

    public function revisar(
        RevisarEvidenciaDocumentalRequest $request,
        FolhaFrequencia $folha,
        FolhaFrequenciaServidor $item,
        EvidenciaDocumental $evidencia,
        EvidenciaDocumentalService $service,
    ): RedirectResponse {
        $this->authorize('conferir', $folha);

        try {
            $evidencia = $service->revisar(
                $folha,
                $item,
                $evidencia,
                EvidenciaStatus::from($request->validated('status')),
                $request->validated('motivo_revisao'),
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['status' => $exception->getMessage()], $request->errorBagName());
        }

        AuditService::registrar(
            'REVISAO_EVIDENCIA',
            'EvidenciaDocumental',
            $evidencia->id,
            "Documento {$evidencia->status->label()} na frequência de {$item->nome}.",
            null,
            $this->dadosAuditoria($evidencia),
        );

        return back()->with('success', 'Análise do documento registrada. Confira novamente a linha do servidor.');
    }

    private function dadosAuditoria(EvidenciaDocumental $evidencia): array
    {
        return [
            'ocorrencia_frequencia_id' => $evidencia->ocorrencia_frequencia_id,
            'folha_frequencia_item_id' => $evidencia->folha_frequencia_item_id,
            'mime_type' => $evidencia->mime_type,
            'tamanho_bytes' => $evidencia->tamanho_bytes,
            'hash_sha256' => $evidencia->hash_sha256,
            'status' => $evidencia->status->value,
        ];
    }
}
