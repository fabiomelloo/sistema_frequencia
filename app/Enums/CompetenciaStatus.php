<?php

namespace App\Enums;

enum CompetenciaStatus: string
{
    case ABERTA = 'ABERTA';
    case FECHADA = 'FECHADA';

    public function label(): string
    {
        return match ($this) {
            self::ABERTA => 'Aberta',
            self::FECHADA => 'Fechada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::ABERTA => 'success',
            self::FECHADA => 'secondary',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
