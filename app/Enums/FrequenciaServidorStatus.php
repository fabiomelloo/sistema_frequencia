<?php

namespace App\Enums;

enum FrequenciaServidorStatus: string
{
    case PENDENTE = 'PENDENTE';
    case INTEGRAL = 'INTEGRAL';
    case COM_FALTAS = 'COM_FALTAS';

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE => 'Pendente',
            self::INTEGRAL => 'Frequência integral',
            self::COM_FALTAS => 'Com faltas',
        };
    }

    public function sigla(): string
    {
        return match ($this) {
            self::PENDENTE => '—',
            self::INTEGRAL => 'INT',
            self::COM_FALTAS => 'FLTs',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::PENDENTE => 'secondary',
            self::INTEGRAL => 'success',
            self::COM_FALTAS => 'warning',
        };
    }
}
