<?php

namespace App\Enums;

enum TipoEvento: string
{
    case ADICIONAL_TURNO = 'ADICIONAL_TURNO';
    case ADICIONAL_NOTURNO = 'ADICIONAL_NOTURNO';
    case INSALUBRIDADE = 'INSALUBRIDADE';
    case PERICULOSIDADE = 'PERICULOSIDADE';
    case GRATIFICACAO = 'GRATIFICACAO';
    case FREQUENCIA = 'FREQUENCIA';
    case OUTROS = 'OUTROS';

    public function label(): string
    {
        return match($this) {
            self::ADICIONAL_TURNO => 'Adicional de Turno',
            self::ADICIONAL_NOTURNO => 'Adicional Noturno',
            self::INSALUBRIDADE => 'Insalubridade',
            self::PERICULOSIDADE => 'Periculosidade',
            self::GRATIFICACAO => 'Gratificação',
            self::FREQUENCIA => 'Frequência',
            self::OUTROS => 'Outros',
        };
    }

    public function exigeVigia(): bool
    {
        return $this === self::ADICIONAL_TURNO;
    }

    public function exigeTrabalhoNoturno(): bool
    {
        return $this === self::ADICIONAL_NOTURNO;
    }

    public function permiteInsalubridade(): bool
    {
        return $this === self::INSALUBRIDADE;
    }

    public function permitePericulosidade(): bool
    {
        return $this === self::PERICULOSIDADE;
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
