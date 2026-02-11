<?php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\User;
use App\Models\LancamentoSetorial;

class NotificacaoService
{
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
                "O lançamento do servidor {$lancamento->servidor->nome} " .
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
                "O lançamento do servidor {$lancamento->servidor->nome} " .
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
        $lancamentos = LancamentoSetorial::whereIn('id', $lancamentoIds)->get();
        $setoresNotificados = [];

        foreach ($lancamentos as $lancamento) {
            $setorId = $lancamento->setor_origem_id;
            if (!in_array($setorId, $setoresNotificados)) {
                $setoresNotificados[] = $setorId;
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
}
