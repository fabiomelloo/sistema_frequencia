<?php

namespace App\Enums;

enum LancamentoStatus: string
{
    case PENDENTE = 'PENDENTE';
    case CONFERIDO_SETORIAL = 'CONFERIDO_SETORIAL';
    case CONFERIDO = 'CONFERIDO';
    case REJEITADO = 'REJEITADO';
    case EXPORTADO = 'EXPORTADO';
    case ESTORNADO = 'ESTORNADO';

    public function label(): string
    {
        return match($this) {
            self::PENDENTE => 'Pendente',
            self::CONFERIDO_SETORIAL => 'Conferido (Setorial)',
            self::CONFERIDO => 'Conferido (Central)',
            self::REJEITADO => 'Rejeitado',
            self::EXPORTADO => 'Exportado',
            self::ESTORNADO => 'Estornado',
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
        };
    }

    public function podeSerEditado(): bool
    {
        return in_array($this, [self::PENDENTE, self::REJEITADO, self::ESTORNADO]);
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
