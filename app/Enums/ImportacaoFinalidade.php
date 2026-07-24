<?php

namespace App\Enums;

enum ImportacaoFinalidade: string
{
    case LANCAMENTOS = 'LANCAMENTOS';
    case OCORRENCIAS = 'OCORRENCIAS';

    public function label(): string
    {
        return match ($this) {
            self::LANCAMENTOS => 'Lançamentos',
            self::OCORRENCIAS => 'Ocorrências de frequência',
        };
    }
}
