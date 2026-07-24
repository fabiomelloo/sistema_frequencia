<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\LancamentoSetorial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class ConferenciaLancamentoService
{
    public function __construct(private readonly GeradorTxtFolhaService $gerador) {}

    public function aprovar(LancamentoSetorial $lancamento, User $usuario): void
    {
        DB::transaction(function () use ($lancamento, $usuario): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);
            $this->validarCompetenciaAberta($lancamento, 'aprovar');

            if (! $lancamento->isConferidoSetorial()) {
                throw new InvalidArgumentException('Apenas lançamentos com status CONFERIDO SETORIAL podem ser aprovados pela Central.');
            }

            $lancamento->forceFill([
                'status' => LancamentoStatus::CONFERIDO,
                'id_validador' => $usuario->id,
                'validated_at' => now(),
            ])->save();

            $lancamento->load(['servidor', 'evento']);
            AuditService::aprovou(
                'LancamentoSetorial',
                $lancamento->id,
                "Lançamento aprovado (Central): {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
            );
            NotificacaoService::lancamentoAprovado($lancamento);
        });
    }

    public function rejeitar(LancamentoSetorial $lancamento, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($lancamento, $motivo, $usuario): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);
            $this->validarCompetenciaAberta($lancamento, 'rejeitar');

            if (! $lancamento->isPendente() && ! $lancamento->isConferidoSetorial()) {
                throw new InvalidArgumentException('Apenas lançamentos PENDENTES ou CONFERIDOS SETORIAL podem ser rejeitados.');
            }

            $lancamento->forceFill([
                'status' => LancamentoStatus::REJEITADO,
                'motivo_rejeicao' => $motivo,
                'id_validador' => $usuario->id,
                'validated_at' => now(),
            ])->save();

            $lancamento->load(['servidor', 'evento']);
            AuditService::rejeitou(
                'LancamentoSetorial',
                $lancamento->id,
                "Lançamento rejeitado: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}. Motivo: {$motivo}"
            );
            NotificacaoService::lancamentoRejeitado($lancamento);
        });
    }

    public function aprovarEmLote(array $ids, User $usuario): array
    {
        return DB::transaction(function () use ($ids, $usuario): array {
            $aprovados = 0;
            $ignorados = 0;
            $competenciaFechada = 0;

            $lancamentos = LancamentoSetorial::query()
                ->whereKey($ids)
                ->with(['servidor', 'evento'])
                ->lockForUpdate()
                ->get();

            foreach ($lancamentos as $lancamento) {
                if (! Competencia::referenciaAberta($lancamento->competencia)) {
                    $competenciaFechada++;

                    continue;
                }

                if (! $lancamento->isConferidoSetorial()) {
                    $ignorados++;

                    continue;
                }

                $antes = $lancamento->toArray();
                $lancamento->forceFill([
                    'status' => LancamentoStatus::CONFERIDO,
                    'id_validador' => $usuario->id,
                    'validated_at' => now(),
                ])->save();

                AuditService::aprovou(
                    'LancamentoSetorial',
                    $lancamento->id,
                    'Lançamento Aprovado em Lote',
                    $antes,
                    $lancamento->toArray()
                );
                NotificacaoService::lancamentoAprovado($lancamento);
                $aprovados++;
            }

            return compact('aprovados', 'ignorados', 'competenciaFechada');
        });
    }

    public function estornar(LancamentoSetorial $lancamento, ?string $motivoInformado): void
    {
        DB::transaction(function () use ($lancamento, $motivoInformado): void {
            $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

            if (! $lancamento->isExportado() && ! $lancamento->isEstornoSolicitado()) {
                throw new InvalidArgumentException('Apenas lançamentos EXPORTADOS ou com ESTORNO SOLICITADO podem ser estornados.');
            }

            $this->validarCompetenciaAberta($lancamento, 'estornar');
            $antes = $lancamento->toArray();
            $motivo = $motivoInformado ?: $lancamento->motivo_estorno ?: $lancamento->motivo_rejeicao ?: '';

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

    public function exportar(string $competencia): array
    {
        if (! Competencia::buscarPorReferencia($competencia)) {
            throw new InvalidArgumentException("A competência {$competencia} não está cadastrada no sistema.");
        }

        $resultado = null;

        try {
            return DB::transaction(function () use ($competencia, &$resultado): array {
                $resultado = $this->gerador->gerar($competencia);
                $ids = $resultado['idsExportados']->toArray();

                LancamentoSetorial::query()->whereKey($ids)->update([
                    'status' => LancamentoStatus::EXPORTADO->value,
                    'exportado_em' => now(),
                ]);

                AuditService::exportou(
                    'LancamentoSetorial',
                    null,
                    "Exportados {$resultado['quantidade']} lançamentos. Arquivo: {$resultado['nomeArquivo']}"
                );
                NotificacaoService::lancamentosExportados($ids);

                return $resultado;
            });
        } catch (Throwable $e) {
            if (is_array($resultado) && isset($resultado['caminhoArquivo'])) {
                Storage::disk('local')->delete($resultado['caminhoArquivo']);
            }

            throw $e;
        }
    }

    private function validarCompetenciaAberta(LancamentoSetorial $lancamento, string $acao): void
    {
        if (! Competencia::referenciaAberta($lancamento->competencia)) {
            throw new InvalidArgumentException("A competência deste lançamento está fechada. Não é possível {$acao}.");
        }
    }
}
