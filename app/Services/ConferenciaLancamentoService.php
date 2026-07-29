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
    public function __construct(
        private readonly GeradorTxtFolhaService $gerador,
        private readonly ProjecaoExportacaoFolhaService $projecaoExportacaoService,
        private readonly EstornoLancamentoService $estornoLancamentoService,
    ) {}

    public function aprovar(LancamentoSetorial $lancamento, User $usuario): void
    {
        DB::transaction(function () use ($lancamento, $usuario): void {
            Competencia::query()->where('referencia', $lancamento->competencia)->lockForUpdate()->firstOrFail();
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
            Competencia::query()->where('referencia', $lancamento->competencia)->lockForUpdate()->firstOrFail();
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

            $referencias = LancamentoSetorial::query()
                ->whereKey($ids)
                ->distinct()
                ->orderBy('competencia')
                ->pluck('competencia');
            Competencia::query()
                ->whereIn('referencia', $referencias)
                ->orderBy('referencia')
                ->lockForUpdate()
                ->get();
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
        $this->estornoLancamentoService->aprovar($lancamento, $motivoInformado);
    }

    public function exportar(string $competencia): array
    {
        $competenciaModel = Competencia::buscarPorReferencia($competencia);
        if (! $competenciaModel) {
            throw new InvalidArgumentException("A competência {$competencia} não está cadastrada no sistema.");
        }

        $resultado = null;

        try {
            return DB::transaction(function () use ($competencia, $competenciaModel, &$resultado): array {
                $this->projecaoExportacaoService->projetarAprovadas($competenciaModel);
                $resultado = $this->gerador->gerar($competencia);
                $ids = $resultado['idsExportados']->toArray();

                AuditService::exportou(
                    'ExportacaoFolha',
                    null,
                    "Exportados {$resultado['quantidade']} itens ".
                    "({$resultado['quantidadeLegada']} legados e {$resultado['quantidadeNativa']} nativos). ".
                    "Arquivo: {$resultado['nomeArquivo']}"
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
