<?php

namespace App\Services;

use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\EventoFolha;
use App\Models\LancamentoSetorial;
use App\Models\PrazoSetorial;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LancamentoSetorialService
{
    public function __construct(private readonly RegrasLancamentoService $regras) {}

    public function criar(array $dados, User $usuario): LancamentoSetorial
    {
        return $this->protectBusinessKey(
            fn (): LancamentoSetorial => DB::transaction(function () use ($dados, $usuario): LancamentoSetorial {
                $servidor = Servidor::findOrFail($dados['servidor_id']);
                $evento = EventoFolha::findOrFail($dados['evento_id']);
                $competencia = $dados['competencia'];

                if (! Competencia::referenciaAberta($competencia)) {
                    throw new InvalidArgumentException("A competência {$competencia} está fechada para novos lançamentos.");
                }

                $this->regras->validar($servidor, $evento, $dados, null, $usuario->setor_id, $usuario);

                $lancamento = LancamentoSetorial::create([
                    ...$this->dadosPersistiveis($dados),
                    'setor_origem_id' => $servidor->setorNaCompetencia($competencia),
                    'criado_por_id' => $usuario->id,
                    'status' => LancamentoStatus::PENDENTE,
                ]);

                AuditService::criou(
                    'LancamentoSetorial',
                    $lancamento->id,
                    "Lançamento criado: {$servidor->nome} - {$evento->descricao} ({$competencia})",
                    $lancamento->toArray()
                );

                return $lancamento;
            }),
            'Já existe um lançamento ativo para este servidor, evento e competência.'
        );
    }

    public function atualizar(LancamentoSetorial $lancamento, array $dados, User $usuario): LancamentoSetorial
    {
        return $this->protectBusinessKey(
            fn (): LancamentoSetorial => DB::transaction(function () use ($lancamento, $dados, $usuario): LancamentoSetorial {
                $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

                if ($lancamento->isRejeitado() && $lancamento->atingiuLimiteRejeicoes()) {
                    throw new InvalidArgumentException('Este lançamento atingiu o limite de rejeições e não pode mais ser re-submetido. Crie um novo lançamento.');
                }

                $servidor = Servidor::findOrFail($dados['servidor_id']);
                $evento = EventoFolha::findOrFail($dados['evento_id']);
                $dados['competencia'] ??= $lancamento->competencia;
                $antes = $lancamento->toArray();

                $this->regras->validar($servidor, $evento, $dados, $lancamento->id, $usuario->setor_id, $usuario);
                $lancamento->update($this->dadosPersistiveis($dados));

                if ($lancamento->isRejeitado()) {
                    $lancamento->forceFill([
                        'status' => LancamentoStatus::PENDENTE,
                        'motivo_rejeicao' => null,
                        'id_validador' => null,
                        'validated_at' => null,
                    ])->save();
                }

                AuditService::editou(
                    'LancamentoSetorial',
                    $lancamento->id,
                    "Lançamento editado: {$servidor->nome} - {$evento->descricao}",
                    $antes,
                    $lancamento->fresh()->toArray()
                );

                return $lancamento->fresh();
            }),
            'Outro lançamento ativo já ocupa esta combinação de servidor, evento e competência.'
        );
    }

    public function excluir(LancamentoSetorial $lancamento): void
    {
        $antes = $lancamento->toArray();
        $lancamento->delete();

        AuditService::excluiu(
            'LancamentoSetorial',
            $lancamento->id,
            "Lançamento excluído (lixeira): servidor_id={$lancamento->servidor_id}, evento_id={$lancamento->evento_id}",
            $antes
        );
    }

    public function restaurar(int $id, User $usuario): LancamentoSetorial
    {
        return $this->protectBusinessKey(
            fn (): LancamentoSetorial => DB::transaction(function () use ($id, $usuario): LancamentoSetorial {
                $lancamento = LancamentoSetorial::onlyTrashed()->lockForUpdate()->findOrFail($id);

                if ($lancamento->setor_origem_id !== $usuario->setor_id) {
                    throw new InvalidArgumentException('Não autorizado.');
                }

                $this->validarCompetenciaEPrazo($lancamento, 'restaurar');
                $this->regras->validar(
                    $lancamento->servidor,
                    $lancamento->evento,
                    $lancamento->toArray(),
                    null,
                    $lancamento->setor_origem_id,
                    $usuario
                );

                $lancamento->restore();
                AuditService::registrar('RESTAUROU', 'LancamentoSetorial', $lancamento->id, 'Lançamento restaurado da lixeira');

                return $lancamento;
            }),
            'Não é possível restaurar: já existe um lançamento ativo para este servidor, evento e competência.'
        );
    }

    public function aprovarSetorial(LancamentoSetorial $lancamento, User $usuario): void
    {
        $this->protectBusinessKey(
            fn () => DB::transaction(function () use ($lancamento, $usuario): void {
                $lancamento = LancamentoSetorial::query()->lockForUpdate()->findOrFail($lancamento->id);

                if ($lancamento->setor_origem_id !== $usuario->setor_id) {
                    throw new InvalidArgumentException('Não autorizado.');
                }

                $this->validarCompetenciaEPrazo($lancamento, 'conferir');

                if ($lancamento->criado_por_id === $usuario->id) {
                    throw new InvalidArgumentException('Você não pode conferir um lançamento que você mesmo criou. Peça a outro usuário do setor.');
                }

                if (! $lancamento->isPendente() && ! $lancamento->isEstornado()) {
                    throw new InvalidArgumentException('Apenas lançamentos PENDENTES ou ESTORNADOS podem ser conferidos pelo setor.');
                }

                $lancamento->forceFill([
                    'status' => LancamentoStatus::CONFERIDO_SETORIAL,
                    'conferido_setorial_por' => $usuario->id,
                    'conferido_setorial_em' => now(),
                ])->save();

                $lancamento->load(['servidor', 'evento']);
                AuditService::registrar(
                    'CONFERIU_SETORIAL',
                    'LancamentoSetorial',
                    $lancamento->id,
                    "Conferido pelo setor: {$lancamento->servidor->nome} - {$lancamento->evento->descricao}"
                );
            }),
            'Não é possível conferir: já existe um lançamento ativo para este servidor, evento e competência.'
        );
    }

    public function aprovarSetorialEmLote(array $ids, User $usuario): array
    {
        return $this->protectBusinessKey(
            fn (): array => DB::transaction(function () use ($ids, $usuario): array {
                $aprovados = 0;
                $ignoradosSegregacao = 0;

                $lancamentos = LancamentoSetorial::query()->whereKey($ids)->lockForUpdate()->get()->keyBy('id');

                foreach ($ids as $id) {
                    $lancamento = $lancamentos->get($id);
                    $statusValido = $lancamento && ($lancamento->isPendente() || $lancamento->isEstornado());

                    if (! $statusValido || $lancamento->setor_origem_id !== $usuario->setor_id) {
                        continue;
                    }

                    if ($lancamento->criado_por_id === $usuario->id) {
                        $ignoradosSegregacao++;

                        continue;
                    }

                    $lancamento->forceFill([
                        'status' => LancamentoStatus::CONFERIDO_SETORIAL,
                        'conferido_setorial_por' => $usuario->id,
                        'conferido_setorial_em' => now(),
                    ])->save();
                    $aprovados++;
                }

                return compact('aprovados', 'ignoradosSegregacao');
            }),
            'O lote contém um lançamento que conflita com outro registro ativo.'
        );
    }

    public function cancelar(LancamentoSetorial $lancamento): void
    {
        $lancamento->forceFill(['status' => LancamentoStatus::CANCELADO])->save();
        AuditService::registrar(
            'CANCELOU',
            'LancamentoSetorial',
            $lancamento->id,
            "Lançamento cancelado pelo usuário: servidor_id={$lancamento->servidor_id}, evento_id={$lancamento->evento_id}"
        );
    }

    public function solicitarEstorno(LancamentoSetorial $lancamento, string $motivo): void
    {
        $lancamento->forceFill([
            'status' => LancamentoStatus::ESTORNO_SOLICITADO,
            'motivo_estorno' => $motivo,
        ])->save();

        AuditService::registrar(
            'SOLICITOU_ESTORNO',
            'LancamentoSetorial',
            $lancamento->id,
            "Solicitação de Estorno registrada: servidor_id={$lancamento->servidor_id}. Motivo: {$motivo}"
        );
    }

    private function validarCompetenciaEPrazo(LancamentoSetorial $lancamento, string $acao): void
    {
        if (! Competencia::referenciaAberta($lancamento->competencia)) {
            throw new InvalidArgumentException("A competência deste lançamento está fechada. Não é possível {$acao}.");
        }

        $competencia = Competencia::buscarPorReferencia($lancamento->competencia);
        if ($competencia && PrazoSetorial::prazoExpirado($competencia->id, $lancamento->setor_origem_id)) {
            throw new InvalidArgumentException("O prazo deste setor para a competência já expirou. Não é possível {$acao}.");
        }
    }

    private function dadosPersistiveis(array $dados): array
    {
        return [
            'servidor_id' => $dados['servidor_id'],
            'evento_id' => $dados['evento_id'],
            'competencia' => $dados['competencia'],
            'dias_trabalhados' => $dados['dias_trabalhados'] ?? null,
            'dias_noturnos' => $dados['dias_noturnos'] ?? null,
            'valor' => $dados['valor'] ?? null,
            'valor_gratificacao' => $dados['valor_gratificacao'] ?? null,
            'porcentagem_insalubridade' => $dados['porcentagem_insalubridade'] ?? null,
            'porcentagem_periculosidade' => $dados['porcentagem_periculosidade'] ?? null,
            'adicional_turno' => $dados['adicional_turno'] ?? null,
            'adicional_noturno' => $dados['adicional_noturno'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
        ];
    }

    private function protectBusinessKey(callable $operation, string $message): mixed
    {
        try {
            return $operation();
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'lancamentos_business_active_unique')) {
                throw $exception;
            }

            throw new InvalidArgumentException($message, previous: $exception);
        }
    }
}
