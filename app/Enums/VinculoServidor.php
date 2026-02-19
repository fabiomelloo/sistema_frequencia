<?php

namespace App\Enums;

enum VinculoServidor: string
{
    case EFETIVO = 'EFETIVO';
    case COMISSIONADO = 'COMISSIONADO';
    case TEMPORARIO = 'TEMPORARIO';
    case CONTRATADO = 'CONTRATADO';

    public function label(): string
    {
        return match($this) {
            self::EFETIVO => 'Efetivo',
            self::COMISSIONADO => 'Comissionado',
            self::TEMPORARIO => 'Temporário',
            self::CONTRATADO => 'Contratado',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
