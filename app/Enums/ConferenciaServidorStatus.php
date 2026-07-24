<?php

namespace App\Enums;

enum ConferenciaServidorStatus: string
{
    case CONFERIDO = 'CONFERIDO';
    case DIVERGENTE = 'DIVERGENTE';

    public function label(): string
    {
        return match ($this) {
            self::CONFERIDO => 'Conferido',
            self::DIVERGENTE => 'Com divergência',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::CONFERIDO => 'success',
            self::DIVERGENTE => 'danger',
        };
    }
}
