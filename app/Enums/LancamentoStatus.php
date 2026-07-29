<?php

namespace App\Enums;

enum LancamentoStatus: string
{
    case PENDENTE = 'PENDENTE';
    case CONFERIDO_SETORIAL = 'CONFERIDO_SETORIAL';
    case CONFERIDO = 'CONFERIDO';
    case REJEITADO = 'REJEITADO'; // Devolvido para correção
    case EXPORTADO = 'EXPORTADO';
    case ESTORNADO = 'ESTORNADO';
    case CANCELADO = 'CANCELADO';
    case ESTORNO_SOLICITADO = 'ESTORNO_SOLICITADO';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::CONFERIDO_SETORIAL => 'Conferido (Setorial)',
            self::CONFERIDO => 'Conferido (Central)',
            self::REJEITADO => 'Correção Solicitada',
            self::EXPORTADO => 'Exportado',
            self::ESTORNADO => 'Estornado',
            self::CANCELADO => 'Cancelado',
            self::ESTORNO_SOLICITADO => 'Estorno Solicitado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::PENDENTE => '#f59e0b',   // amber / warning
            self::CONFERIDO_SETORIAL => '#0ea5e9',   // sky-blue / info
            self::CONFERIDO => '#10b981',   // emerald / success
            self::REJEITADO => '#ef4444',   // red / danger
            self::EXPORTADO => '#6c757d',   // gray / secondary
            self::ESTORNADO => '#1e293b',   // dark slate
            self::CANCELADO => '#dc2626',   // red-darker
            self::ESTORNO_SOLICITADO => '#d97706',   // orange-amber
        };
    }

    public function podeSerEditado(): bool
    {
        return in_array($this, [self::PENDENTE, self::REJEITADO]);
    }

    public function podeSerAprovadoSetorial(): bool
    {
        return in_array($this, [self::PENDENTE, self::ESTORNADO]); // Assuming Estornado needs setorial approval again
    }

    public function podeSerAprovadoCentral(): bool
    {
        return $this === self::CONFERIDO_SETORIAL;
    }

    public function podeSerRejeitado(): bool
    {
        return in_array($this, [self::PENDENTE, self::CONFERIDO_SETORIAL]);
    }

    public function podeSerExportado(): bool
    {
        return $this === self::CONFERIDO;
    }

    public function podeSerEstornado(): bool
    {
        return $this === self::EXPORTADO;
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Alias de compatibilidade
     */
    public function podeSerAprovado(): bool
    {
        return $this->podeSerAprovadoCentral();
    }
}
