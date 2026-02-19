<?php

namespace App\Enums;

enum UserRole: string
{
    case CENTRAL = 'CENTRAL';
    case SETORIAL = 'SETORIAL';
    case ADMIN = 'ADMIN';
    case GESTOR = 'GESTOR';
    case AUDITOR = 'AUDITOR';

    /**
     * Retorna o label legível para exibição
     */
    public function label(): string
    {
        return match($this) {
            self::CENTRAL => 'Central',
            self::SETORIAL => 'Setorial',
            self::ADMIN => 'Administrador',
            self::GESTOR => 'Gestor Setorial',
            self::AUDITOR => 'Auditor',
        };
    }

    /**
     * Retorna descrição do papel
     */
    public function descricao(): string
    {
        return match($this) {
            self::CENTRAL => 'Usuário da Central com acesso ao Painel de Conferência',
            self::SETORIAL => 'Usuário Setorial com acesso aos Lançamentos',
            self::ADMIN => 'Administrador com acesso total ao sistema',
            self::GESTOR => 'Gestor Setorial com aprovação e gerenciamento do setor',
            self::AUDITOR => 'Auditor com acesso somente leitura para auditoria',
        };
    }

    /**
     * Retorna array de valores para validação
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Retorna array associativo para selects
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Verifica se o role tem acesso ao Painel de Conferência
     */
    public function temAcessoPainel(): bool
    {
        return in_array($this, [self::CENTRAL, self::ADMIN]);
    }

    /**
     * Verifica se o role pode fazer lançamentos
     */
    public function podeFazerLancamentos(): bool
    {
        return in_array($this, [self::SETORIAL, self::GESTOR]);
    }

    /**
     * Verifica se o role pode gerenciar configurações do sistema
     */
    public function podeGerenciarSistema(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Verifica se o role pode aprovar lançamentos no nível setorial
     */
    public function podeAprovarSetorial(): bool
    {
        return in_array($this, [self::GESTOR, self::SETORIAL]);
    }

    /**
     * Verifica se o role tem acesso somente leitura (auditoria)
     */
    public function somenteAuditoria(): bool
    {
        return $this === self::AUDITOR;
    }
}
