<?php

namespace App\Services;

use App\Enums\CompetenciaStatus;
use App\Enums\LancamentoStatus;
use App\Models\Competencia;
use App\Models\FolhaFrequencia;
use App\Models\LancamentoSetorial;
use App\Models\Notificacao;
use App\Models\PrazoSetorial;
use App\Models\User;

class NotificacaoService
{
    public static function folhaFrequenciaAprovada(FolhaFrequencia $folha): void
    {
        self::notificarSetorDaFolha(
            $folha,
            'FREQUENCIA_APROVADA',
            'Frequência mensal aprovada',
            "A frequência de {$folha->competencia->descricao} foi aprovada pela Central.",
        );
    }

    public static function folhaFrequenciaDevolvida(FolhaFrequencia $folha): void
    {
        self::notificarSetorDaFolha(
            $folha,
            'FREQUENCIA_DEVOLVIDA',
            'Frequência devolvida para correção',
            "A frequência de {$folha->competencia->descricao} foi devolvida. Orientação: {$folha->motivo_devolucao}",
        );
    }

    private static function notificarSetorDaFolha(
        FolhaFrequencia $folha,
        string $tipo,
        string $titulo,
        string $mensagem,
    ): void {
        foreach (User::where('setor_id', $folha->setor_id)->get() as $usuario) {
            self::criar($usuario->id, $tipo, $titulo, $mensagem, route('frequencia.show', $folha));
        }
    }

    /**
     * Cria uma notificação para um usuário.
     */
    public static function criar(
        int $userId,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?string $link = null
    ): Notificacao {
        return Notificacao::create([
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'link' => $link,
        ]);
    }

    /**
     * Notifica os usuários do setor sobre aprovação de lançamento.
     */
    public static function lancamentoAprovado(LancamentoSetorial $lancamento): void
    {
        $usuariosSetor = User::where('setor_id', $lancamento->setor_origem_id)->get();

        foreach ($usuariosSetor as $usuario) {
            self::criar(
                $usuario->id,
                'APROVADO',
                'Lançamento Aprovado',
                "O lançamento do servidor {$lancamento->servidor->nome} ".
                "({$lancamento->evento->descricao}) foi aprovado.",
                route('lancamentos.show', $lancamento)
            );
        }
    }

    /**
     * Notifica os usuários do setor sobre rejeição de lançamento.
     */
    public static function lancamentoRejeitado(LancamentoSetorial $lancamento): void
    {
        $usuariosSetor = User::where('setor_id', $lancamento->setor_origem_id)->get();

        foreach ($usuariosSetor as $usuario) {
            self::criar(
                $usuario->id,
                'REJEITADO',
                'Lançamento Rejeitado',
                "O lançamento do servidor {$lancamento->servidor->nome} ".
                "({$lancamento->evento->descricao}) foi rejeitado. Motivo: {$lancamento->motivo_rejeicao}",
                route('lancamentos.show', $lancamento)
            );
        }
    }

    /**
     * Notifica os usuários do setor sobre exportação.
     */
    public static function lancamentosExportados(array $lancamentoIds): void
    {
        // Eager-load para evitar N+1 ao acessar servidor/evento
        $setorIds = LancamentoSetorial::whereIn('id', $lancamentoIds)
            ->distinct()
            ->pluck('setor_origem_id');

        foreach ($setorIds as $setorId) {
            $usuarios = User::where('setor_id', $setorId)->get();

            foreach ($usuarios as $usuario) {
                self::criar(
                    $usuario->id,
                    'EXPORTADO',
                    'Lançamentos Exportados',
                    'Lançamentos do seu setor foram exportados para a folha de pagamento.',
                    route('lancamentos.index')
                );
            }
        }
    }

    /**
     * Notifica os usuários do setor sobre estorno de lançamento.
     */
    public static function lancamentoEstornado(LancamentoSetorial $lancamento, string $motivo): void
    {
        $usuariosSetor = User::where('setor_id', $lancamento->setor_origem_id)->get();

        foreach ($usuariosSetor as $usuario) {
            self::criar(
                $usuario->id,
                'ESTORNADO',
                'Lançamento Estornado',
                "O lançamento do servidor {$lancamento->servidor->nome} ".
                "({$lancamento->evento->descricao}) foi estornado. Motivo: {$motivo}",
                route('lancamentos.show', $lancamento)
            );
        }
    }

    /**
     * Retorna contagem de notificações não lidas do usuário.
     */
    public static function contarNaoLidas(int $userId): int
    {
        return Notificacao::where('user_id', $userId)
            ->whereNull('lida_em')
            ->count();
    }

    /**
     * C5: Notifica usuários de setores cujo prazo está próximo do vencimento.
     * Chamada pelo scheduler diariamente.
     */
    public static function notificarPrazosProximos(int $diasAntecedencia = 3): int
    {
        $competenciaAberta = Competencia::where('status', CompetenciaStatus::ABERTA->value)->first();
        if (! $competenciaAberta) {
            return 0;
        }

        $dataLimiteGlobal = $competenciaAberta->data_limite;

        // Buscar setores que ainda têm pendentes
        $setoresComPendentes = LancamentoSetorial::where('competencia', $competenciaAberta->referencia)
            ->whereIn('status', [
                LancamentoStatus::PENDENTE->value,
                LancamentoStatus::REJEITADO->value,
            ])
            ->distinct('setor_origem_id')
            ->pluck('setor_origem_id');

        $notificados = 0;
        foreach ($setoresComPendentes as $setorId) {
            // Verifica prazo setorial específico, se existir
            $prazoSetorial = PrazoSetorial::obterPrazo($competenciaAberta->id, $setorId);
            $dataLimite = ($prazoSetorial && $prazoSetorial->data_limite)
                ? $prazoSetorial->data_limite
                : $dataLimiteGlobal;

            if (! $dataLimite) {
                continue;
            }

            $diasRestantes = now()->diffInDays($dataLimite, false);
            if ($diasRestantes > $diasAntecedencia || $diasRestantes < 0) {
                continue;
            }

            $usuarios = User::where('setor_id', $setorId)->get();
            foreach ($usuarios as $usuario) {
                // Evitar notificações duplicadas no mesmo dia
                $jaNotificado = Notificacao::where('user_id', $usuario->id)
                    ->where('tipo', 'PRAZO_PROXIMO')
                    ->whereDate('created_at', today())
                    ->exists();

                if (! $jaNotificado) {
                    $origemPrazo = $prazoSetorial ? 'setorial' : 'geral';
                    self::criar(
                        $usuario->id,
                        'PRAZO_PROXIMO',
                        'Prazo Próximo do Vencimento',
                        "A competência {$competenciaAberta->referencia} vence em {$diasRestantes} dia(s) ".
                        "({$dataLimite->format('d/m/Y')}). Prazo {$origemPrazo}. Finalize seus lançamentos pendentes.",
                        route('lancamentos.index')
                    );
                    $notificados++;
                }
            }
        }

        return $notificados;
    }
}
