<?php

namespace App\Enums;

enum LancamentoStatus: string
{
    case PENDENTE = 'PENDENTE';
    case CONFERIDO = 'CONFERIDO';
    case REJEITADO = 'REJEITADO';
    case EXPORTADO = 'EXPORTADO';

    public function label(): string
    {
        return match($this) {
            self::PENDENTE => 'Pendente',
            self::CONFERIDO => 'Conferido',
            self::REJEITADO => 'Rejeitado',
            self::EXPORTADO => 'Exportado',
        };
    }

    public function podeSerEditado(): bool
    {
        return in_array($this, [self::PENDENTE, self::REJEITADO]);
    }

    public function podeSerAprovado(): bool
    {
        return $this === self::PENDENTE;
    }

    public function podeSerRejeitado(): bool
    {
        return $this === self::PENDENTE;
    }

    public function podeSerExportado(): bool
    {
        return $this === self::CONFERIDO;
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
