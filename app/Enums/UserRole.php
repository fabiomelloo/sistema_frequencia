<?php

namespace App\Enums;

enum UserRole: string
{
    case CENTRAL = 'CENTRAL';
    case SETORIAL = 'SETORIAL';

    /**
     * Retorna o label legível para exibição
     */
    public function label(): string
    {
        return match($this) {
            self::CENTRAL => 'Central',
            self::SETORIAL => 'Setorial',
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
        return $this === self::CENTRAL;
    }

    /**
     * Verifica se o role pode fazer lançamentos
     */
    public function podeFazerLancamentos(): bool
    {
        return $this === self::SETORIAL;
    }
}
