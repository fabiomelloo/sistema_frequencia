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
        return match($this) {
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
        return match($this) {
            self::PENDENTE => 'warning',
            self::CONFERIDO_SETORIAL => 'info',
            self::CONFERIDO => 'success',
            self::REJEITADO => 'danger',
            self::EXPORTADO => 'secondary',
            self::ESTORNADO => 'dark',
            self::CANCELADO => 'danger',
            self::ESTORNO_SOLICITADO => 'warning',
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
        return in_array($this, [self::PENDENTE, self::CONFERIDO_SETORIAL, self::ESTORNADO]);
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
