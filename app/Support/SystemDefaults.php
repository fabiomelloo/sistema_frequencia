<?php

namespace App\Support;

/**
 * Constantes de valores padrão do sistema.
 *
 * Centraliza todos os defaults que também podem ser sobrescritos
 * pela tabela `configuracoes` (Model Configuracao::get()).
 *
 * Uso: Configuracao::getInt('sla_dias_conferencia', SystemDefaults::SLA_DIAS_CONFERENCIA)
 */
final class SystemDefaults
{
    // ─── SLA ─────────────────────────────────────────────────────────────────

    /** Dias máximos para que um lançamento receba conferência antes do SLA vencer */
    public const SLA_DIAS_CONFERENCIA = 5;

    /** Dias antes do vencimento do SLA em que aparece o alerta */
    public const SLA_DIAS_ALERTA = 3;

    // ─── Lançamentos ────────────────────────────────────────────────────────

    /** Número máximo de rejeições que um lançamento pode acumular antes de ser bloqueado */
    public const LIMITE_REJEICOES_LANCAMENTO = 3;

    /** Valor máximo permitido (R$) para o Adicional Noturno */
    public const TETO_ADICIONAL_NOTURNO = 500.00;

    /** Número de meses retroativos permitidos para lançamentos de usuários não-admin */
    public const MESES_RETROATIVOS = 3;

    // ─── Delegações ─────────────────────────────────────────────────────────

    /** Número máximo de delegações ativas simultaneamente por setor */
    public const LIMITE_DELEGACOES_SETOR = 3;

    /** Duração máxima de uma delegação, em dias */
    public const DURACAO_MAXIMA_DELEGACAO_DIAS = 90;

    // ─── Constructor privado para classe utilitária ──────────────────────────

    private function __construct() {}
}
