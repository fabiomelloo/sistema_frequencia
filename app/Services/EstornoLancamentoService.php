<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Models\LancamentoSetorial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EstornoLancamentoService
{
    public function solicitar(LancamentoSetorial $lancamento, string $motivo): void
    {
        DB::transaction(function () use ($lancamento, $motivo): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

            if (! $lancamento->isExportado()) {
                throw new InvalidArgumentException('Apenas lançamentos EXPORTADOS podem ter estorno solicitado.');
            }

            $antes = $lancamento->toArray();
            $lancamento->forceFill([
                'status' => LancamentoStatus::ESTORNO_SOLICITADO,
                'motivo_estorno' => trim($motivo),
            ])->save();

            AuditService::registrar(
                'SOLICITOU_ESTORNO',
                'LancamentoSetorial',
                $lancamento->id,
                'Solicitação de estorno registrada. Motivo: '.trim($motivo),
                $antes,
                $lancamento->fresh()->toArray()
            );
        });
    }

    public function aprovar(LancamentoSetorial $lancamento, ?string $motivoInformado = null): void
    {
        DB::transaction(function () use ($lancamento, $motivoInformado): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

            if (! $lancamento->isEstornoSolicitado()) {
                throw new InvalidArgumentException('Apenas lançamentos com ESTORNO SOLICITADO podem ser estornados.');
            }

            $motivo = trim($motivoInformado ?: $lancamento->motivo_estorno ?: '');
            if ($motivo === '') {
                throw new InvalidArgumentException('O motivo do estorno é obrigatório.');
            }

            $antes = $lancamento->toArray();
            $lancamento->forceFill([
                'status' => LancamentoStatus::ESTORNADO,
                'motivo_estorno' => $motivo,
                'exportado_em' => null,
                'id_validador' => null,
                'validated_at' => null,
                'conferido_setorial_por' => null,
                'conferido_setorial_em' => null,
            ])->save();

            $lancamento->load(['servidor', 'evento']);
            AuditService::registrar(
                'ESTORNOU',
                'LancamentoSetorial',
                $lancamento->id,
                "Lançamento estornado — Motivo: {$motivo}",
                $antes,
                $lancamento->fresh()->toArray()
            );
            NotificacaoService::lancamentoEstornado($lancamento, $motivo);
        });
    }

    public function recusar(LancamentoSetorial $lancamento, string $motivo): void
    {
        DB::transaction(function () use ($lancamento, $motivo): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

            if (! $lancamento->isEstornoSolicitado()) {
                throw new InvalidArgumentException('Apenas solicitações de estorno pendentes podem ser recusadas.');
            }

            $antes = $lancamento->toArray();
            $lancamento->forceFill([
                'status' => LancamentoStatus::EXPORTADO,
                'motivo_estorno' => null,
            ])->save();

            AuditService::registrar(
                'RECUSOU_ESTORNO',
                'LancamentoSetorial',
                $lancamento->id,
                'Solicitação de estorno recusada. Motivo: '.trim($motivo),
                $antes,
                $lancamento->fresh()->toArray()
            );
        });
    }
}
